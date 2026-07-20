<?php

namespace App\Domain\CRM\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandReviewDecision;
use App\Domain\CRM\Models\BrandStatusHistory;
use App\Domain\CRM\Models\BrandVersion;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * محرّك حالة العلامة — يعمل بالأحداث/الإجراءات (لا Dropdown يدوي).
 * الحالات: draft→submitted→under_review→approved | changes_requested→(draft)→resubmit.
 * كل انتقال: precondition + Transaction + history(append-only) + audit + (إشعار عبر الطابور).
 */
class BrandWorkflowService
{
    /** انتقالات مسموحة لكل حالة (المصدر ⇐ الوجهات). */
    private const ALLOWED = [
        'draft' => ['submitted'],
        'changes_requested' => ['submitted'],
        'submitted' => ['under_review'],
        'under_review' => ['approved', 'changes_requested'],
        'approved' => ['suspended', 'archived'],
        'suspended' => ['approved', 'archived'],
    ];

    /**
     * `$clientId` صار اختياريًّا.
     *
     * كان إلزاميًّا حين كانت العلامة تُنشأ من صفحة عميل حصرًا. والعلامة
     * المسجِّلة لنفسها **لا عميل لها**: هي في مستأجرها الخاصّ، وملكيّتها في
     * صفّ `owner` لا في `client_id`. تمرير معرّف عميل وهميّ هنا كان سيعيد
     * الحلّ المرفوض صراحةً — خلط العميل بالعلامة، وتشويه التقارير والفوترة.
     */
    public function createDraft(int $tenantId, ?int $clientId, array $data, int $actorId): Brand
    {
        return DB::transaction(function () use ($tenantId, $clientId, $data, $actorId) {
            return TenantContext::withTenant($tenantId, function () use ($actorId, $clientId, $data, $tenantId) {
                $brand = Brand::create($data + ['tenant_id' => $tenantId, 'client_id' => $clientId, 'status' => 'draft',
                    'slug' => Str::slug($data['name'] ?? 'brand').'-'.Str::lower(Str::random(4)), 'current_version' => 1, 'created_by' => $actorId]);
                $this->recordStatus($brand, null, 'draft', $actorId, 'إنشاء مسودة');
                AuditLogger::log('brand.created', $brand, [], $tenantId, $actorId);

                return $brand;
            });
        });
    }

    public function updateDraft(Brand $brand, array $data, int $actorId): Brand
    {
        if (! $brand->isEditableByClient()) {
            throw new RuntimeException('لا يمكن تعديل العلامة في حالتها الحالية.');
        }
        TenantContext::withTenant($brand->tenant_id, function () use ($actorId, $brand, $data) {
            $brand->update($data + ['updated_by' => $actorId]);
            AuditLogger::log('brand.updated', $brand, array_keys($data), $brand->tenant_id, $actorId);
        });

        return $brand->fresh();
    }

    /** إرسال (draft/changes_requested → submitted): يُنشئ Version جديدة. */
    public function submit(Brand $brand, int $actorId): Brand
    {
        return DB::transaction(function () use ($brand, $actorId) {
            return TenantContext::withTenant($brand->tenant_id, function () use ($actorId, $brand) {
                // قفل الصف وإعادة قراءة الحالة (يمنع إصدارين متزامنين ويؤكّد التحقّق على حالة القاعدة)
                $brand = Brand::query()->whereKey($brand->getKey())->lockForUpdate()->first() ?? $brand;
                $from = $brand->status;
                $this->assertTransition($from, 'submitted');
                $isResubmit = $from === 'changes_requested';
                $version = $isResubmit ? $brand->current_version + 1 : $brand->current_version;
                BrandVersion::create(['tenant_id' => $brand->tenant_id, 'brand_id' => $brand->id, 'version' => $version,
                    'snapshot' => $brand->only(['name', 'sector', 'website', 'description', 'tone_of_voice', 'target_audience', 'preferred_language', 'prohibited_topics', 'required_messages', 'visual_guidelines', 'contact_information']),
                    'created_by' => $actorId, 'created_at' => now()]);
                $brand->update(['status' => 'submitted', 'submitted_at' => now(), 'current_version' => $version, 'changes_reason' => null]);
                $this->recordStatus($brand, $from, 'submitted', $actorId, $isResubmit ? 'إعادة إرسال (إصدار جديد)' : 'إرسال للمراجعة');
                AuditLogger::log('brand.submitted', $brand, ['version' => $version], $brand->tenant_id, $actorId);

                return $brand->fresh();
            });
        });
    }

    /** إجراءات الوكالة. */
    public function startReview(Brand $brand, int $reviewerId): Brand
    {
        return $this->transition($brand, 'under_review', $reviewerId, 'بدء المراجعة');
    }

    public function approve(Brand $brand, int $reviewerId, ?string $note = null): Brand
    {
        $b = $this->transition($brand, 'approved', $reviewerId, 'اعتماد العلامة', ['reviewed_at' => now(), 'reviewed_by' => $reviewerId]);
        $this->recordDecision($b, $reviewerId, 'approved', $note);

        return $b;
    }

    public function requestChanges(Brand $brand, int $reviewerId, string $reason): Brand
    {
        $b = $this->transition($brand, 'changes_requested', $reviewerId, $reason, ['changes_reason' => $reason, 'reviewed_at' => now(), 'reviewed_by' => $reviewerId]);
        $this->recordDecision($b, $reviewerId, 'changes_requested', $reason);

        return $b;
    }

    public function suspend(Brand $brand, int $reviewerId, ?string $reason = null): Brand
    {
        $b = $this->transition($brand, 'suspended', $reviewerId, $reason ?? 'تعليق');
        $this->recordDecision($b, $reviewerId, 'suspended', $reason);

        return $b;
    }

    public function archive(Brand $brand, int $actorId): Brand
    {
        // الأرشفة متاحة من أي حالة
        // `$from` يُسنَد داخل الإغلاق — و`use ($from)` كان يستورد متغيّرًا غير
        // معرَّف في هذا النطاق، فيُطلق تحذيرًا ويُمرّر null قبل أن يُظلَّل.
        TenantContext::withTenant($brand->tenant_id, function () use ($actorId, $brand) {
            $from = $brand->status;
            $brand->update(['status' => 'archived']);
            $this->recordStatus($brand, $from, 'archived', $actorId, 'أرشفة');
            $this->recordDecision($brand, $actorId, 'archived', null);
            AuditLogger::log('brand.archived', $brand, [], $brand->tenant_id, $actorId);
        });

        return $brand->fresh();
    }

    private function transition(Brand $brand, string $to, int $actorId, string $reason, array $extra = []): Brand
    {
        return DB::transaction(function () use ($brand, $to, $actorId, $reason, $extra) {
            return TenantContext::withTenant($brand->tenant_id, function () use ($actorId, $brand, $extra, $reason, $to) {
                // قفل الصف وإعادة قراءة الحالة من القاعدة قبل التحقّق (لا اعتماد على حالة قديمة/متزامنة)
                $brand = Brand::query()->whereKey($brand->getKey())->lockForUpdate()->first() ?? $brand;
                $from = $brand->status;
                $this->assertTransition($from, $to);
                $brand->update(['status' => $to] + $extra);
                $this->recordStatus($brand, $from, $to, $actorId, $reason);
                AuditLogger::log("brand.$to", $brand, ['from' => $from], $brand->tenant_id, $actorId);

                return $brand->fresh();
            });
        });
    }

    private function assertTransition(string $from, string $to): void
    {
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new RuntimeException("انتقال غير مسموح: {$from} → {$to}");
        }
    }

    private function recordStatus(Brand $brand, ?string $from, string $to, int $actorId, ?string $reason): void
    {
        BrandStatusHistory::create(['tenant_id' => $brand->tenant_id, 'brand_id' => $brand->id, 'from_status' => $from,
            'to_status' => $to, 'actor_id' => $actorId, 'reason' => $reason, 'request_id' => request()?->headers->get('X-Request-Id'), 'occurred_at' => now()]);
    }

    private function recordDecision(Brand $brand, int $reviewerId, string $decision, ?string $note): void
    {
        BrandReviewDecision::create(['tenant_id' => $brand->tenant_id, 'brand_id' => $brand->id, 'reviewer_id' => $reviewerId,
            'decision' => $decision, 'note' => $note, 'version' => $brand->current_version, 'created_at' => now()]);
    }
}
