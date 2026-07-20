<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable, CampaignStatusHistory};
use App\Domain\CRM\Models\Brand;
use App\Domain\Creators\Models\Creator;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * منشئ الحملات: آلة حالة بالأحداث + إدارة المخرجات.
 * draft→planning/cancelled؛ planning→active/cancelled؛ active→paused/completed/cancelled؛ paused→active/cancelled.
 */
class CampaignWorkflowService
{
    private const ALLOWED = [
        'draft' => ['planning', 'cancelled'],
        'planning' => ['active', 'cancelled'],
        'active' => ['paused', 'completed', 'cancelled'],
        'paused' => ['active', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function create(int $tenantId, array $data, int $actorId): Campaign
    {
        return DB::transaction(function () use ($tenantId, $data, $actorId) {
            return TenantContext::withTenant($tenantId, function () use ($tenantId, $data, $actorId) {
                $c = Campaign::create($data + [
                    'tenant_id' => $tenantId, 'status' => 'draft', 'created_by' => $actorId,
                    'campaign_number' => 'CM-' . $tenantId . '-' . (Campaign::where('tenant_id', $tenantId)->count() + 1),
                ]);
                $this->recordStatus($c, null, 'draft', $actorId, 'إنشاء الحملة');
                AuditLogger::log('campaign.created', $c, [], $tenantId, $actorId);

                return $c;
            });
        });
    }

    /** يشتقّ حملة (مسودة) من طلب خدمة من نوع حملة، ويربطها ويشير للطلب في التنفيذ. */
    public function convertFromRequest(ServiceRequest $request, int $actorId): Campaign
    {
        if ($request->type !== 'campaign') {
            throw new RuntimeException('يمكن تحويل طلبات الحملات فقط.');
        }
        return DB::transaction(function () use ($request, $actorId) {
            return TenantContext::withTenant($request->tenant_id, function () use ($request, $actorId) {
                if (! $request->client_id) throw new RuntimeException('الطلب بلا عميل محدّد.');
                $existing = Campaign::where('source_request_id', $request->id)->first();
                if ($existing) return $existing; // idempotent
                // ينتقل موجز الطلب كما هو: لا يُعاد إدخال ما قاله العميل مرّة أخرى
                $c = $this->create($request->tenant_id, [
                    'client_id' => $request->client_id, 'brand_id' => $request->brand_id,
                    'source_request_id' => $request->id, 'name' => $request->title,
                    'objective' => $request->description,
                    'budget_minor' => (int) ($request->budget_minor ?? 0),
                    'currency' => $request->currency ?: 'SAR',
                    'start_date' => $request->preferred_start_date,
                    'end_date' => $request->preferred_end_date,
                ], $actorId);
                AuditLogger::log('campaign.converted_from_request', $c, ['request_id' => $request->id], $request->tenant_id, $actorId);

                return $c;
            });
        });
    }

    public function updateDraft(Campaign $c, array $data, int $actorId): Campaign
    {
        if (! $c->isEditable()) throw new RuntimeException('لا يمكن تعديل الحملة في حالتها الحالية.');
        return TenantContext::withTenant($c->tenant_id, function () use ($actorId, $c, $data) {
            $c->update($data);
            AuditLogger::log('campaign.updated', $c, array_keys($data), $c->tenant_id, $actorId);
            return $c;
        });
    }

    public function plan(Campaign $c, int $actorId): Campaign { return $this->transition($c, 'planning', $actorId); }
    public function activate(Campaign $c, int $actorId): Campaign
    {
        // لا تُفعَّل حملة بلا مخرجات
        $count = TenantContext::withTenant($c->tenant_id, function () use ($c) {
            $count = $c->deliverables()->count();
            return $count;
        });
        if ($count === 0) throw new RuntimeException('لا يمكن تفعيل حملة بلا مخرجات.');
        return $this->transition($c, 'active', $actorId);
    }
    public function pause(Campaign $c, int $actorId, ?string $reason = null): Campaign { return $this->transition($c, 'paused', $actorId, $reason); }
    public function resume(Campaign $c, int $actorId): Campaign { return $this->transition($c, 'active', $actorId); }
    /**
     * الإغلاق يتطلّب أن تكون الالتزامات مُغلَقة فعلًا.
     *
     * كان `completed` بلا شرط: تُغلَق الحملة وفيها تعاون معروض لم يُقبل، ومحتوى
     * عالق في المراجعة، وفاتورة لم تُحصَّل، ومستحقّ لم يُصرف. الإغلاق حينها
     * يُخفي عملًا قائمًا ومالًا لم يُدفع بدل أن يُنهيهما.
     *
     * المنع يقول ما المتبقّي بالضبط — لا «لا يمكن الإغلاق» وحدها.
     */
    public function complete(Campaign $c, int $actorId, ?string $note = null): Campaign
    {
        $blockers = $this->openObligations($c);
        if ($blockers) {
            throw new RuntimeException('لا تُغلَق الحملة وفيها التزامات مفتوحة: ' . implode(' · ', $blockers));
        }

        return $this->transition($c, 'completed', $actorId, $note);
    }

    /**
     * ما يمنع الإغلاق، بعبارات قابلة للتنفيذ.
     *
     * @return array<string>
     */
    public function openObligations(Campaign $c): array
    {
        [$liveCollabs, $openContent, $unpaidInvoices, $openPayouts] = TenantContext::withTenant($c->tenant_id, function () use ($c) {
            $liveCollabs = \App\Domain\Collaborations\Models\Collaboration::where('campaign_id', $c->id)
                ->whereIn('status', ['offered', 'accepted', 'in_progress', 'submitted'])->count();
            $openContent = \App\Domain\Content\Models\ContentItem::where('campaign_id', $c->id)
                ->whereIn('status', ['submitted', 'agency_review', 'client_review', 'changes_requested'])->count();
            $unpaidInvoices = \App\Domain\Finance\Models\Invoice::where('campaign_id', $c->id)
                ->whereIn('status', \App\Domain\Finance\Models\Invoice::OPEN)->count();
            $openPayouts = \App\Domain\Finance\Models\Payout::where('campaign_id', $c->id)
                ->whereNotIn('status', ['paid', 'cancelled'])->count();

            return [$liveCollabs, $openContent, $unpaidInvoices, $openPayouts];
        });

        return array_values(array_filter([
            $liveCollabs ? "{$liveCollabs} تعاونًا لم يُغلَق" : null,
            $openContent ? "{$openContent} محتوى في المراجعة" : null,
            $unpaidInvoices ? "{$unpaidInvoices} فاتورة لم تُحصَّل" : null,
            $openPayouts ? "{$openPayouts} مستحقًّا لم يُصرف" : null,
        ]));
    }
    public function cancel(Campaign $c, int $actorId, ?string $reason = null): Campaign { return $this->transition($c, 'cancelled', $actorId, $reason); }

    /** يضيف مخرجًا (مسموح فقط في draft/planning). يتحقّق أن المبدع من نفس المستأجر. */
    public function addDeliverable(Campaign $c, array $data, int $actorId): CampaignDeliverable
    {
        if (! $c->isEditable()) throw new RuntimeException('لا يمكن إضافة مخرجات في حالة الحملة الحالية.');
        return TenantContext::withTenant($c->tenant_id, function () use ($actorId, $c, $data) {
            if (! empty($data['creator_id'])) {
                $ok = Creator::where('id', $data['creator_id'])->exists();
                if (! $ok) { throw new RuntimeException('المبدع غير موجود في هذا المستأجر.'); }
            }
            $status = ! empty($data['creator_id']) ? 'assigned' : 'planned';
            $d = CampaignDeliverable::create($data + ['tenant_id' => $c->tenant_id, 'campaign_id' => $c->id, 'status' => $status, 'currency' => $c->currency ?: 'SAR']);
            AuditLogger::log('campaign.deliverable_added', $c, ['deliverable_id' => $d->id], $c->tenant_id, $actorId);
            return $d;
        });
    }

    public function removeDeliverable(Campaign $c, int $deliverableId, int $actorId): void
    {
        if (! $c->isEditable()) throw new RuntimeException('لا يمكن حذف مخرجات في حالة الحملة الحالية.');
        TenantContext::withTenant($c->tenant_id, function () use ($actorId, $c, $deliverableId) {
            CampaignDeliverable::where('id', $deliverableId)->where('campaign_id', $c->id)->delete();
            AuditLogger::log('campaign.deliverable_removed', $c, ['deliverable_id' => $deliverableId], $c->tenant_id, $actorId);
        });
    }

    private function transition(Campaign $c, string $to, int $actorId, ?string $reason = null): Campaign
    {
        return DB::transaction(function () use ($c, $to, $actorId, $reason) {
            return TenantContext::withTenant($c->tenant_id, function () use ($c, $to, $actorId, $reason) {
                // قفل الصف وإعادة قراءة الحالة من القاعدة (لا اعتماد على حالة قديمة/متزامنة)
                $c = Campaign::query()->whereKey($c->getKey())->lockForUpdate()->first() ?? $c;
                $from = $c->status;
                if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
                    throw new RuntimeException("انتقال غير مسموح: {$from} → {$to}");
                }
                $c->status = $to;
                $c->save();
                $this->recordStatus($c, $from, $to, $actorId, $reason);
                AuditLogger::log("campaign.$to", $c, [], $c->tenant_id, $actorId);

                return $c;
            });
        });
    }

    private function recordStatus(Campaign $c, ?string $from, string $to, int $actorId, ?string $reason): void
    {
        CampaignStatusHistory::create([
            'tenant_id' => $c->tenant_id, 'campaign_id' => $c->id,
            'from_status' => $from, 'to_status' => $to, 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now(),
        ]);
    }
}
