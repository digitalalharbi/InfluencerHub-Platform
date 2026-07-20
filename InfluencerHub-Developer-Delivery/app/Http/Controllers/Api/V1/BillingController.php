<?php
namespace App\Http\Controllers\Api\V1;
use App\Domain\Billing\Models\{Plan, Subscription};
use App\Domain\Billing\Services\{EntitlementService, UsageMeterService};
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BillingController extends Controller {
    public function __construct(private EntitlementService $entitlements, private UsageMeterService $usage) {}

    private function org(Request $r): ?Organization {
        return TenantContext::organizationId() ? Organization::find(TenantContext::organizationId()) : null;
    }

    /** حالة اشتراك المؤسسة الحالية. */
    public function subscription(Request $r) {
        $org = $this->org($r);
        if (! $org) return response()->json(['message' => 'لا سياق مؤسسة'], 422);
        $sub = Subscription::where('organization_id', $org->id)->latest()->first();
        return response()->json(['data' => $sub ? [
            'status' => $sub->status, 'plan_version_id' => $sub->plan_version_id,
            'trial_ends_at' => $sub->trial_ends_at, 'current_period_end' => $sub->current_period_end,
            'is_active' => $sub->isActiveLike(),
        ] : null]);
    }

    /** Entitlements المحسوبة لمجموعة ميزات. */
    public function entitlements(Request $r) {
        $org = $this->org($r);
        if (! $org) return response()->json(['message' => 'لا سياق مؤسسة'], 422);
        $features = ['users.max','creators.max','customers.max','campaigns.active.max','storage.gb',
            'integrations.max','exports.monthly.max','automation.runs.monthly.max','api.requests.monthly.max',
            'white_label.enabled','custom_domain.enabled','advanced_analytics.enabled','external_portals.enabled','marketplace.enabled'];
        $out = [];
        foreach ($features as $f) { $out[$f] = $this->entitlements->resolve($org, $f); }
        return response()->json(['data' => $out]);
    }

    /** ملخّص الاستهلاك الحالي. */
    public function usage(Request $r) {
        $org = $this->org($r);
        if (! $org) return response()->json(['message' => 'لا سياق مؤسسة'], 422);
        $metered = ['exports.monthly.max','automation.runs.monthly.max','api.requests.monthly.max'];
        $out = [];
        foreach ($metered as $f) {
            $out[$f] = ['used' => $this->usage->currentUsage($org, $f), 'remaining' => $this->usage->remaining($org, $f)];
        }
        return response()->json(['data' => $out]);
    }

    /** إدارة (system_admin): قائمة الخطط ونسخها. */
    public function plans(Request $r) {
        abort_unless(optional($r->user('sanctum'))->is_system_admin, 403, 'يتطلب صلاحية مدير المنصة');
        return response()->json(['data' => Plan::with('versions.entitlements')->get()]);
    }
}
