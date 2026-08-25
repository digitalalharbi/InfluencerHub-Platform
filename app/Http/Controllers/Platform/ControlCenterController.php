<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مركز تحكّم مالك المنصّة (§3) — الصفحة الرئيسية لمساحة /platform. يعرض نظرة
 * عابرة للمستأجرين من بياناتٍ فعلية فقط (لا KPIs مزيّفة): المستأجرون، المؤسسات،
 * المستخدمون، الحملات، الاشتراكات، نشاط المنصّة الأخير، وأحداث الأمان. كل عدد
 * يُحسب بـTenantContext::withBypass (المالك عابر للنطاق). الإدارة التفصيلية
 * (المستأجرون/الخطط/الاشتراكات/التدقيق) تبقى في صفحات النظام القائمة — لا تُكرَّر.
 */
class ControlCenterController extends Controller
{
    /** أحداث تُعدّ أمنية في سجلّ التدقيق (دخول/تجاوز سياق/تقمّص لاحقًا). */
    private const SECURITY_ACTIONS = ['auth.login', 'auth.failed', 'tenant.bypass.system_admin', 'platform.impersonate.start', 'platform.impersonate.stop'];

    public function __invoke(): Response
    {
        [$stats, $tenantsByStatus, $recentTenants, $recentActivity, $securityEvents] = TenantContext::withBypass(function () {
            $stats = [
                'tenants' => Tenant::count(),
                'activeTenants' => Tenant::where('status', 'active')->count(),
                'organizations' => Organization::withoutGlobalScopes()->count(),
                'users' => User::withoutGlobalScopes()->count(),
                'activeUsers' => User::withoutGlobalScopes()->where('is_active', true)->count(),
                'campaigns' => Campaign::withoutGlobalScopes()->count(),
                'activeSubscriptions' => Subscription::withoutGlobalScopes()->whereIn('status', ['trialing', 'active'])->count(),
            ];

            $tenantsByStatus = Tenant::selectRaw('status, count(*) c')->groupBy('status')->get()
                ->map(fn ($r) => ['status' => $r->status, 'label' => __("statuses.{$r->status}"), 'tone' => __("statuses.tone.{$r->status}"), 'count' => (int) $r->c])
                ->values();

            $recentTenants = Tenant::latest()->limit(8)->get()->map(fn (Tenant $t) => [
                'id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'type' => $t->type,
                'status' => $t->status, 'statusLabel' => __("statuses.{$t->status}"), 'statusTone' => __("statuses.tone.{$t->status}"),
                'orgs' => Organization::withoutGlobalScopes()->where('tenant_id', $t->id)->count(),
            ])->values();

            $recentActivity = AuditLog::withoutGlobalScopes()->latest('occurred_at')->limit(12)->get()->map(fn (AuditLog $a) => [
                'action' => $a->action, 'actor' => $a->actor_name, 'tenantId' => $a->tenant_id,
                'at' => $a->occurred_at?->format('Y-m-d H:i'),
            ])->values();

            $securityEvents = AuditLog::withoutGlobalScopes()->whereIn('action', self::SECURITY_ACTIONS)
                ->latest('occurred_at')->limit(10)->get()->map(fn (AuditLog $a) => [
                    'action' => $a->action, 'actor' => $a->actor_name, 'ip' => $a->ip,
                    'at' => $a->occurred_at?->format('Y-m-d H:i'),
                ])->values();

            return [$stats, $tenantsByStatus, $recentTenants, $recentActivity, $securityEvents];
        });

        // تدقيق وصول مالك المنصّة إلى مركز التحكّم (§10) — لا باب صامت.
        \App\Domain\Audit\Services\AuditLogger::log('platform.control_center.view', null, [], null, request()->user()?->id);

        return Inertia::render('Platform/ControlCenter', [
            'stats' => $stats,
            'tenantsByStatus' => $tenantsByStatus,
            'recentTenants' => $recentTenants,
            'recentActivity' => $recentActivity,
            'securityEvents' => $securityEvents,
            // روابط إلى صفحات الإدارة القائمة (لا تُكرَّر واجهاتها هنا).
            'links' => [
                'tenants' => '/beta/admin/tenants',
                'subscriptions' => '/beta/admin/subscriptions',
                'audit' => '/beta/admin/audit',
                'systemHealth' => '/app/system-health',
                'integrations' => '/app/integrations',
            ],
        ]);
    }
}
