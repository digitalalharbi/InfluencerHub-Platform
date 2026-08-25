<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\ExternalAgencyMember;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Platform\Services\PlatformPortalEligibilityService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مبدّل المستأجرين لمالك المنصّة (§4) — دليل مستأجرين للبحث والاختيار، ثم نظرة
 * منصّية على المستأجر (مؤسساته/مستخدميه/حملاته/اشتراكه/بوّاباته المتاحة/نشاطه).
 * اختيار المستأجر لا يغيّر هوية الحساب — يُدخل سياق المعاينة عبر رابط لكل مستأجر
 * (متعدّد النوافذ آمن). المعاينة العميقة داخل البوّابة تُضاف في P3.
 */
class PlatformTenantController extends Controller
{
    public function __construct(private PlatformPortalEligibilityService $eligibility)
    {
    }

    public function index(Request $r): Response
    {
        $data = TenantContext::withBypass(function () use ($r) {
            $q = Tenant::query();
            if ($s = trim((string) $r->query('q'))) {
                $q->where(fn ($w) => $w->where('name', 'ilike', "%{$s}%")->orWhere('slug', 'ilike', "%{$s}%"));
            }
            if ($st = $r->query('status')) {
                $q->where('status', $st);
            }
            $tenants = $q->latest()->paginate(24)->through(fn (Tenant $t) => [
                'id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'type' => $t->type,
                'status' => $t->status, 'statusLabel' => __("statuses.{$t->status}"), 'statusTone' => __("statuses.tone.{$t->status}"),
                'orgs' => Organization::withoutGlobalScopes()->where('tenant_id', $t->id)->count(),
                'href' => "/platform/tenants/{$t->id}",
            ]);
            return ['tenants' => $tenants, 'total' => Tenant::count()];
        });

        return Inertia::render('Platform/Tenants', $data + ['filters' => ['q' => $r->query('q'), 'status' => $r->query('status')]]);
    }

    public function show(Tenant $tenant): Response
    {
        $data = TenantContext::withBypass(function () use ($tenant) {
            $orgIds = Organization::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('id');
            $memberUserIds = OrganizationMembership::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'active')->distinct()->pluck('user_id');

            $orgs = Organization::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->map(fn (Organization $o) => [
                'id' => $o->id, 'name' => $o->name, 'type' => $o->type, 'status' => $o->status,
                'members' => OrganizationMembership::withoutGlobalScopes()->where('organization_id', $o->id)->where('status', 'active')->count(),
            ])->values();

            // البوّابات المتاحة فعلًا لهذا المستأجر — مصدر واحد يطابق حرّاس البوّابات
            // (لا منطق أهلية مكرّر، §5/§ P2-hardening).
            $portals = $this->eligibility->tenantPortals($tenant->id);

            $sub = Subscription::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('status', ['trialing', 'active'])->latest()->first();

            $activity = AuditLog::withoutGlobalScopes()->where('tenant_id', $tenant->id)->latest('occurred_at')->limit(12)->get()
                ->map(fn (AuditLog $a) => ['action' => $a->action, 'actor' => $a->actor_name, 'at' => $a->occurred_at?->format('Y-m-d H:i')])->values();

            return [
                'tenant' => [
                    'id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug, 'type' => $tenant->type,
                    'status' => $tenant->status, 'statusLabel' => __("statuses.{$tenant->status}"), 'statusTone' => __("statuses.tone.{$tenant->status}"),
                    'mode' => $tenant->deployment_mode,
                ],
                'stats' => [
                    'organizations' => $orgIds->count(),
                    'users' => $memberUserIds->count(),
                    'campaigns' => Campaign::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
                    'hasSubscription' => (bool) $sub,
                ],
                'orgs' => $orgs,
                'portals' => $portals,
                'activity' => $activity,
            ];
        });

        \App\Domain\Audit\Services\AuditLogger::log('platform.tenant.view', $tenant, [], $tenant->id, request()->user()?->id);

        return Inertia::render('Platform/TenantDetail', $data);
    }
}
