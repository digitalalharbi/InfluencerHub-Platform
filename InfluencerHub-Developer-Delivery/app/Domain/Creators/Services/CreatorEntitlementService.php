<?php
namespace App\Domain\Creators\Services;
use App\Domain\Billing\Services\{EntitlementService, UsageMeterService};
use App\Domain\Creators\Models\{CreatorApplication, CreatorApplicationPlatform};
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use RuntimeException;

/** إنفاذ حدود المبدعين الخمسة. كلها tenant-scoped، atomic، idempotent، قابلة لإعادة الحساب. */
class CreatorEntitlementService {
    public function __construct(private EntitlementService $ent, private UsageMeterService $usage, private ApplicationDocumentService $docs) {}

    public function orgForTenant(int $tenantId): ?Organization {
        return TenantContext::withBypass(fn () => Organization::where('tenant_id', $tenantId)->orderBy('id')->first());
    }

    /** ينفّذ الاستعلامات ضمن سياق مستأجر المؤسسة (البوابة العامة بلا سياق). */
    private function scoped(Organization $org, callable $fn) {
        return TenantContext::withTenant($org->tenant_id, $fn, $org->id);
    }

    /**
     * ugc_creator.enabled: يمنع اختيار قدرة UGC إن لم تُفعَّل الميزة.
     *
     * البوابة على القدرة نفسها لا على النص المشتقّ منها: النص القديم يسقط
     * القدرات الإنتاجية (تصوير، مونتاج) على `ugc_creator`، فلو حكمنا عليه
     * لمنعنا مصوّرًا لم يطلب UGC أصلًا من التقديم.
     *
     * @param array<int,string> $capabilities
     */
    public function assertCapabilitiesAllowed(Organization $org, array $capabilities): void {
        if (! in_array('ugc', $capabilities, true)) return;
        $allowed = $this->scoped($org, fn () => $this->ent->allows($org, 'ugc_creator.enabled'));
        if (! $allowed) {
            throw new RuntimeException('خطة الوكالة لا تدعم صنّاع UGC حاليًا.');
        }
    }

    /** توافق خلفي مع المتّصلين الذين ما زالوا يمرّرون النوع القديم نصًّا. */
    public function assertUgcAllowed(Organization $org, string $accountType): void {
        $this->assertCapabilitiesAllowed(
            $org,
            \App\Domain\Creators\Services\CreatorCapabilityService::LEGACY_TO_CAPS[$accountType] ?? []
        );
    }

    /** creator_portal.enabled: يمنع دخول/تفعيل بوابة المبدع. */
    public function portalEnabled(Organization $org): bool {
        return $this->scoped($org, fn () => $this->ent->allows($org, 'creator_portal.enabled'));
    }

    /** social_integrations.max: يحدّ عدد المنصّات المرتبطة بالطلب. */
    public function assertSocialWithinLimit(Organization $org, CreatorApplication $app): void {
        $this->scoped($org, function () use ($org, $app) {
            $res = $this->ent->resolve($org, 'social_integrations.max');
            if ($res['unlimited']) return;
            $limit = $res['limit'] ?? 0;
            $count = CreatorApplicationPlatform::where('application_id', $app->id)->count();
            if ($count >= $limit) throw new RuntimeException("تم بلوغ حدّ المنصّات ({$limit}).");
        });
    }

    /** creator_storage.gb: يرفض الرفع قبل تجاوز التخزين (بايت). */
    public function assertStorageAvailable(Organization $org, int $incomingBytes): void {
        $this->scoped($org, function () use ($org, $incomingBytes) {
            $res = $this->ent->resolve($org, 'creator_storage.gb');
            if ($res['unlimited']) return;
            $limitBytes = (int) ($res['limit'] ?? 0) * 1024 * 1024 * 1024;
            if ($limitBytes <= 0) throw new RuntimeException('لا مساحة تخزين متاحة في الخطة.');
            $used = $this->docs->tenantStorageBytes($org->tenant_id);
            if ($used + $incomingBytes > $limitBytes) throw new RuntimeException('تجاوز حدّ التخزين (creator_storage.gb).');
        });
    }

    /** creator_applications.monthly.max: يُستهلَك عند الإرسال (idempotent per application). */
    public function consumeSubmission(Organization $org, CreatorApplication $app, ?int $actorId = null): void {
        $this->scoped($org, fn () =>
            $this->usage->consume($org, 'creator_applications.monthly.max', 1, 'creator-application:submit:' . $app->id, $actorId));
    }
}
