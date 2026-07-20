<?php
namespace App\Domain\Billing\Services;
use App\Domain\Billing\Models\{Subscription, PlanEntitlement, OrganizationAddOn};
use App\Domain\Tenancy\Models\Organization;

/** المصدر المركزي الوحيد لفحص القيود. ممنوع توزيع شروط الخطة في Controllers/Views. */
class EntitlementService {
    /** يعيد ['allowed'=>bool, 'unlimited'=>bool, 'limit'=>?int] لميزة/مؤسسة. */
    public function resolve(Organization $org, string $featureKey): array {
        $mode = config('influencerhub.deployment_mode', 'saas');

        // self_hosted: ترخيص محلي (config)؛ فارغ = غير محدود.
        if ($mode === 'self_hosted') {
            $self = config('influencerhub.self_hosted_entitlements', []);
            if (array_key_exists($featureKey, $self)) {
                $v = $self[$featureKey];
                return $v === true ? ['allowed'=>true,'unlimited'=>true,'limit'=>null]
                                   : ['allowed'=>true,'unlimited'=>false,'limit'=>(int)$v];
            }
            return ['allowed'=>true,'unlimited'=>true,'limit'=>null];
        }

        // saas/dedicated: من الاشتراك الفعّال (trialing|active)
        $sub = Subscription::where('organization_id', $org->id)
            ->whereIn('status', ['trialing','active'])->latest()->first();
        if (! $sub) {
            return ['allowed'=>false,'unlimited'=>false,'limit'=>0]; // لا اشتراك = لا ميزات مدفوعة
        }

        $ent = PlanEntitlement::where('plan_version_id', $sub->plan_version_id)
            ->where('feature_key', $featureKey)->first();
        $unlimited = (bool) ($ent?->is_unlimited);
        $limit = (int) ($ent?->value ?? 0);
        $allowedBool = $ent ? ((bool) $ent->value || $unlimited) : false;

        // overrides على الاشتراك (enterprise/dedicated)
        $ov = $sub->overrides[$featureKey] ?? null;
        if ($ov !== null) {
            if ($ov === 'unlimited') { $unlimited = true; }
            elseif (is_bool($ov)) { $allowedBool = $ov; }
            else { $limit = (int) $ov; $allowedBool = $ov > 0; }
        }

        // Add-ons: زيادة رقمية أو تفعيل boolean
        $addons = OrganizationAddOn::where('organization_id', $org->id)->where('status','active')->with('addOn')->get();
        foreach ($addons as $oa) {
            if ($oa->addOn && $oa->addOn->feature_key === $featureKey) {
                if ($oa->addOn->grant_boolean) { $allowedBool = true; }
                if ($oa->addOn->grant_value)  { $limit += (int) $oa->addOn->grant_value * (int) $oa->quantity; $allowedBool = true; }
            }
        }

        return [
            'allowed'   => $unlimited ? true : $allowedBool,
            'unlimited' => $unlimited,
            'limit'     => $unlimited ? null : (int) $limit,
        ];
    }

    public function allows(Organization $org, string $featureKey): bool {
        return $this->resolve($org, $featureKey)['allowed'];
    }
    /** null = غير محدود. */
    public function limit(Organization $org, string $featureKey): ?int {
        $r = $this->resolve($org, $featureKey);
        return $r['unlimited'] ? null : (int) $r['limit'];
    }
}
