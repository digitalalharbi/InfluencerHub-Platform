<?php

namespace App\Domain\Brands\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * تفويض وكالة بإدارة علامة — من الطرفين، وبنطاق محدَّد.
 *
 * ## التفويض ليس ملكية
 *
 * صفّ `owner` لا يُمَسّ هنا أبدًا. الوكالة تحصل على `managing_agency` بنطاق
 * خدمات صريح، والعلامة تبقى في مستأجرها ملكًا لأصحابها. ولذلك **الإلغاء لا
 * يحذف شيئًا**: ينهي العلاقة ويبقي كل ما أُنتج تحتها — حملات وعقود وفواتير —
 * في مكانه. وكالةٌ تُصرَف لا تأخذ معها عمل سنة.
 *
 * ## ولا وصول شامل افتراضيًّا
 *
 * النطاق الفارغ يعني **لا شيء** لا «كل شيء». `grants()` يرفض ما لم يُذكر
 * صراحةً. والدعوة بلا نطاق مرفوضة من الأصل: تفويضٌ مفتوح يُمنح مرّة ولا
 * يُراجَع أبدًا.
 *
 * ## والموافقة من الطرف الآخر شرط
 *
 * الداعي لا يُنشئ علاقة فعّالة بنفسه — يُنشئ طلبًا `pending`. ولو جاز للوكالة
 * أن تمنح نفسها وصولًا لعلامة، أو للعلامة أن تُلزم وكالةً بعمل، لَما كان
 * للتفويض معنى.
 */
class AgencyDelegationService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * العلامة تدعو وكالة.
     *
     * @param  array<int,string>  $services
     */
    public function inviteAgency(Brand $brand, int $agencyTenantId, array $services, User $actor): BrandWorkspaceRelationship
    {
        $this->assertBrandOwner($brand, $actor);

        return $this->openInvitation($brand, $agencyTenantId, $services, $actor, 'brand');
    }

    /**
     * الوكالة تدعو العلامة.
     *
     * الفارق الوحيد عن الاتّجاه الآخر هو من يوافق — والقيود واحدة.
     *
     * @param  array<int,string>  $services
     */
    public function requestDelegation(Brand $brand, int $agencyTenantId, array $services, User $actor): BrandWorkspaceRelationship
    {
        $this->assertAgencyMember($agencyTenantId, $actor);

        return $this->openInvitation($brand, $agencyTenantId, $services, $actor, 'agency');
    }

    /**
     * الطرف الآخر يوافق — وهنا وحدها يصير التفويض نافذًا.
     */
    public function approve(BrandWorkspaceRelationship $rel, User $actor): BrandWorkspaceRelationship
    {
        if ($rel->status !== 'pending') {
            throw new RuntimeException('هذا التفويض ليس بانتظار موافقة.');
        }

        return DB::transaction(function () use ($rel, $actor) {
            $locked = BrandWorkspaceRelationship::whereKey($rel->getKey())->lockForUpdate()->first();

            if ($locked->status !== 'pending') {
                throw new RuntimeException('عولج هذا الطلب بالفعل.');
            }

            $brand = TenantContext::withBypass(
                fn () => Brand::withoutGlobalScopes()->findOrFail($locked->brand_id),
            );

            // الموافقة على الطرف **المقابل** للداعي — وإلّا وافق الداعي على نفسه
            $invitedBy = $locked->invited_by;
            $inviterIsBrandSide = TenantContext::withBypass(
                fn () => OrganizationMembership::withoutGlobalScopes()
                    ->where('user_id', $invitedBy)->where('tenant_id', $brand->tenant_id)->exists(),
            );

            if ($inviterIsBrandSide) {
                $this->assertAgencyMember($locked->tenant_id, $actor);
            } else {
                $this->assertBrandOwner($brand, $actor);
            }

            $locked->update([
                'status' => 'active',
                'started_at' => now(),
                'approved_by' => $actor->id,
            ]);

            AuditLogger::log('brand_delegation.approved', $locked, [], $brand->tenant_id, $actor->id, [
                'agency_tenant_id' => $locked->tenant_id,
                'services' => $locked->services_scope,
            ]);

            $this->notifyBothSides($brand, $locked, 'صار التفويض نافذًا',
                'يمكن للوكالة الآن العمل ضمن النطاق المتّفق عليه.');

            return $locked->fresh();
        });
    }

    /** يُرفض الطلب — بلا أثر على أيّ بيانات. */
    public function decline(BrandWorkspaceRelationship $rel, User $actor, ?string $reason = null): BrandWorkspaceRelationship
    {
        if ($rel->status !== 'pending') {
            throw new RuntimeException('هذا التفويض ليس بانتظار موافقة.');
        }

        $rel->update(['status' => 'declined', 'ended_at' => now()]);

        AuditLogger::log('brand_delegation.declined', $rel, [], null, $actor->id, ['reason' => $reason]);

        return $rel->fresh();
    }

    /**
     * يعدّل النطاق المفوَّض.
     *
     * تضييق النطاق يسري فورًا — وهو المقصود: سحبُ صلاحية يجب ألّا ينتظر دورة
     * موافقة أخرى.
     *
     * @param  array<int,string>  $services
     */
    public function updateScope(BrandWorkspaceRelationship $rel, array $services, User $actor): BrandWorkspaceRelationship
    {
        $brand = TenantContext::withBypass(
            fn () => Brand::withoutGlobalScopes()->findOrFail($rel->brand_id),
        );

        $this->assertBrandOwner($brand, $actor);
        $services = $this->assertServices($services);

        $before = $rel->services_scope ?? [];
        $rel->update(['services_scope' => $services]);

        AuditLogger::log('brand_delegation.scope_changed', $rel, [], $brand->tenant_id, $actor->id, [
            'old' => $before, 'new' => $services,
        ]);

        return $rel->fresh();
    }

    /**
     * إلغاء التفويض — **بلا فقد بيانات**.
     *
     * تُختم العلاقة بـ`ended_at` ولا تُحذف: سجلّها هو الدليل على من كان مخوَّلًا
     * ومتى، وحذفه يمحو إجابة سؤالٍ سيُطرح يومًا. وكل ما أُنتج تحتها يبقى معلّقًا
     * بالعلامة في مستأجرها.
     */
    public function revoke(BrandWorkspaceRelationship $rel, User $actor, ?string $reason = null): BrandWorkspaceRelationship
    {
        if ($rel->relationship_type === BrandWorkspaceRelationship::OWNER) {
            throw new RuntimeException('لا تُلغى علاقة الملكية — العلامة تملك نفسها.');
        }

        if (! $rel->isLive()) {
            throw new RuntimeException('هذا التفويض غير فعّال أصلًا.');
        }

        $brand = TenantContext::withBypass(
            fn () => Brand::withoutGlobalScopes()->findOrFail($rel->brand_id),
        );

        $this->assertBrandOwner($brand, $actor);

        $rel->update(['status' => 'ended', 'ended_at' => now()]);

        AuditLogger::log('brand_delegation.revoked', $rel, [], $brand->tenant_id, $actor->id, [
            'agency_tenant_id' => $rel->tenant_id, 'reason' => $reason,
        ]);

        $this->notifyBothSides($brand, $rel, 'أُنهي التفويض',
            'انتهى وصول الوكالة. البيانات المنتَجة تبقى في مساحة العلامة.');

        return $rel->fresh();
    }

    // ===== داخلي =====

    /** @param array<int,string> $services */
    private function openInvitation(Brand $brand, int $agencyTenantId, array $services, User $actor, string $side): BrandWorkspaceRelationship
    {
        $services = $this->assertServices($services);

        if ($agencyTenantId === $brand->tenant_id) {
            throw new RuntimeException('لا تُفوَّض العلامة لمستأجرها نفسه.');
        }

        $agency = TenantContext::withBypass(fn () => Tenant::find($agencyTenantId));

        if (! $agency || $agency->type !== Tenant::TYPE_AGENCY) {
            throw new RuntimeException('الوكالة غير موجودة.');
        }

        $existing = BrandWorkspaceRelationship::where('brand_id', $brand->id)
            ->where('tenant_id', $agencyTenantId)
            ->where('relationship_type', BrandWorkspaceRelationship::MANAGING_AGENCY)
            ->first();

        // `isLive()` تعني «فعّال» وحدها ولا تشمل `pending` — فلا تصلح حارسًا هنا،
        // وإلّا دهست دعوةٌ ثانية دعوةً معلّقة بصمت والرسالة تدّعي أنها منعتها.
        if ($existing && in_array($existing->status, ['active', 'pending'], true) && ! $existing->ended_at) {
            throw new RuntimeException('يوجد تفويض قائم أو بانتظار الموافقة مع هذه الوكالة.');
        }

        // ما انتهى أو رُفض يُعاد فتحه على الصفّ نفسه: الفهرس الفريد
        // (brand, tenant, type) يمنع صفًّا ثانيًا، وتاريخ العلاقة يبقى واحدًا.

        $rel = $existing
            ? tap($existing)->update([
                'status' => 'pending', 'services_scope' => $services,
                'invited_by' => $actor->id, 'started_at' => null, 'ended_at' => null,
            ])
            : BrandWorkspaceRelationship::create([
                'brand_id' => $brand->id,
                'tenant_id' => $agencyTenantId,
                'relationship_type' => BrandWorkspaceRelationship::MANAGING_AGENCY,
                'status' => 'pending',
                'services_scope' => $services,
                'permissions_scope' => ['operate'],
                'invited_by' => $actor->id,
            ]);

        AuditLogger::log('brand_delegation.invited', $rel, [], $brand->tenant_id, $actor->id, [
            'side' => $side, 'agency_tenant_id' => $agencyTenantId, 'services' => $services,
        ]);

        $this->notifyBothSides($brand, $rel, 'دعوة تفويض',
            'وصلت دعوة لإدارة علامة ضمن نطاق محدَّد — تحتاج موافقتك.');

        return $rel->fresh();
    }

    /**
     * النطاق مطلوب وصريح.
     *
     * @param  array<int,string>  $services
     * @return array<int,string>
     */
    private function assertServices(array $services): array
    {
        $services = array_values(array_unique(array_filter($services)));

        if ($services === []) {
            throw new RuntimeException('حدّد نطاق الخدمات — التفويض بلا نطاق لا يُمنح.');
        }

        $unknown = array_diff($services, BrandWorkspaceRelationship::SERVICES);

        if ($unknown !== []) {
            throw new RuntimeException('خدمة غير معروفة: '.implode('، ', $unknown));
        }

        return $services;
    }

    /** مالك العلامة وحده يفوّض ويسحب — لا عضو عاديّ. */
    private function assertBrandOwner(Brand $brand, User $actor): void
    {
        $ok = TenantContext::withBypass(
            fn () => OrganizationMembership::withoutGlobalScopes()
                ->where('tenant_id', $brand->tenant_id)
                ->where('user_id', $actor->id)
                ->where('role', Role::BrandAdmin->value)
                ->where('status', 'active')
                ->exists(),
        );

        if (! $ok) {
            throw new RuntimeException('هذا الإجراء لمالك العلامة وحده.');
        }
    }

    private function assertAgencyMember(int $agencyTenantId, User $actor): void
    {
        $ok = TenantContext::withBypass(
            fn () => OrganizationMembership::withoutGlobalScopes()
                ->where('tenant_id', $agencyTenantId)
                ->where('user_id', $actor->id)
                ->whereIn('role', [Role::AgencyAdmin->value, Role::SuperAdmin->value])
                ->where('status', 'active')
                ->exists(),
        );

        if (! $ok) {
            throw new RuntimeException('هذا الإجراء لمدير الوكالة وحده.');
        }
    }

    private function notifyBothSides(Brand $brand, BrandWorkspaceRelationship $rel, string $title, string $body): void
    {
        TenantContext::withBypass(function () use ($brand, $rel, $title, $body) {
            foreach ([$brand->tenant_id, $rel->tenant_id] as $tenantId) {
                $admins = OrganizationMembership::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('role', [Role::BrandAdmin->value, Role::AgencyAdmin->value])
                    ->where('status', 'active')
                    ->pluck('user_id')->unique();

                foreach ($admins as $userId) {
                    $this->notifications->notify((int) $tenantId, (int) $userId,
                        'brand_delegation', 'general', $title, $body, null, [], $rel);
                }
            }
        });
    }
}
