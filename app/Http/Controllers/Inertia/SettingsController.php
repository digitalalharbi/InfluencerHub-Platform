<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Billing\Models\{PlanEntitlement, Subscription};
use App\Domain\Billing\Services\UsageMeterService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الإعدادات والاشتراك (React/Inertia) — مساحة العمل + الخطة + الحقوق والاستهلاك (عرض).
 * بوابة على مستوى الإدارة (لا يراها الأدوار العرضية) — البيانات المالية حسّاسة. معزول بالمستأجر.
 */
class SettingsController extends Controller
{
    /** أدوار تُدير إعدادات الحساب/الفوترة. */
    private const ADMIN_ROLES = ['super_admin', 'agency_admin', 'operations_manager'];

    private const FEATURE_LABEL = [
        'customers.max' => 'حد العملاء', 'creators.max' => 'حد المبدعين', 'campaigns.max' => 'حد الحملات',
        'team.seats' => 'مقاعد الفريق', 'storage.gb' => 'التخزين (GB)', 'creator_portal.enabled' => 'بوابة المبدع',
        'api.calls' => 'استدعاءات API', 'creator_storage.gb' => 'تخزين المبدعين (GB)',
        'social_integrations.max' => 'تكاملات المنصّات', 'ugc_creator.enabled' => 'صنّاع محتوى UGC',
        'creator_applications.monthly.max' => 'طلبات انضمام شهريًا',
    ];

    /** أسماء مزوّدي الفوترة بالعربية (بيانات العرض تُوسم بصدق). */
    private const PROVIDER_LABEL = [
        'manual' => 'يدوي', 'fake' => 'بيانات عرض (تجريبي)', 'moyasar' => 'ميسر', 'stripe' => 'Stripe',
    ];

    public function index(UsageMeterService $usage): Response
    {
        $orgId = TenantContext::organizationId();
        /** @var User $user */
        $user = auth()->user();
        abort_unless($orgId && in_array($user->roleIn($orgId), self::ADMIN_ROLES, true), 403);
        $org = Organization::find($orgId);
        $sub = $org ? Subscription::where('organization_id', $org->id)->whereIn('status', ['trialing', 'active'])->latest()->first() : null;

        $entitlements = [];
        if ($sub) {
            $rows = PlanEntitlement::where('plan_version_id', $sub->plan_version_id)->get();
            foreach ($rows as $e) {
                $isBool = str_ends_with($e->feature_key, '.enabled');
                $limit = $e->is_unlimited ? null : (int) $e->value;
                $used = ($limit !== null && $org) ? $usage->currentUsage($org, $e->feature_key) : null;
                $entitlements[] = [
                    'key' => $e->feature_key,
                    'label' => self::FEATURE_LABEL[$e->feature_key] ?? $e->feature_key,
                    'unlimited' => (bool) $e->is_unlimited,
                    'bool' => $isBool,
                    'enabled' => $isBool ? (bool) $e->value : null,
                    'limit' => $limit,
                    'used' => $used,
                    'pct' => ($limit && $limit > 0 && $used !== null) ? (int) round(min(100, $used / $limit * 100)) : 0,
                ];
            }
        }

        $planVersion = $sub?->planVersion;

        // لمحة الفريق داخل الإعدادات (بيانات فعلية) — تربط بصفحة الفريق
        $teamRows = $org ? OrganizationMembership::withoutGlobalScopes()
            ->where('organization_id', $org->id)->where('status', 'active')->get() : collect();
        $teamUsers = \App\Domain\Identity\Models\User::whereIn('id', $teamRows->pluck('user_id'))->pluck('name', 'id');
        $roleLabel = \App\Http\Controllers\Inertia\TeamController::ROLE_LABEL;
        $teamPreview = $teamRows->take(6)->map(fn ($m) => [
            'name' => $teamUsers[$m->user_id] ?? '—',
            'role' => $roleLabel[$m->role] ?? $m->role,
        ])->values();
        $byRole = $teamRows->groupBy('role')->map(fn ($g, $r) => [
            'label' => $roleLabel[$r] ?? $r, 'count' => $g->count(),
        ])->values();
        $team = $org ? OrganizationMembership::withoutGlobalScopes()->where('organization_id', $org->id)->where('status', 'active')->count() : 0;

        return Inertia::render('Settings/Index', [
            'org' => [
                'name' => $org?->name ?? '—',
                'type' => $org?->type ?? '—',
                'team' => $team,
                'showcase' => $org?->tenant?->slug === 'showcase',
            ],
            'subscription' => $sub ? [
                'status' => $sub->status,
                'statusLabel' => $sub->status === 'trialing' ? 'تجربة مجانية' : ($sub->status === 'active' ? 'نشط' : $sub->status),
                'plan' => $planVersion?->plan?->name ?? '—',
                'version' => (int) ($planVersion?->version ?? 0),
                'trialEndsAt' => $sub->trial_ends_at?->format('Y-m-d'),
                'periodStart' => $sub->current_period_start?->format('Y-m-d'),
                'periodEnd' => $sub->current_period_end?->format('Y-m-d'),
                'provider' => self::PROVIDER_LABEL[$sub->billing_provider] ?? ($sub->billing_provider ?? 'يدوي'),
            ] : null,
            'entitlements' => $entitlements,
            'teamPreview' => $teamPreview,
            'byRole' => $byRole,
        ]);
    }
}
