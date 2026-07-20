<?php

namespace App\Http\Controllers\Inertia\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * لوحة مدير النظام (SaaS) — إشراف عبر المستأجرين، للقراءة فقط (لا انتحال هوية ولا إجراءات هدّامة).
 * محميّة بـ EnsureSystemAdmin. كل الاستعلامات تتجاوز نطاق المستأجر للإشراف.
 */
class PlatformController extends Controller
{
    /** أسماء مزوّدي الفوترة بالعربية — لا يُعرض المفتاح الخام في الواجهة. */
    private const PROVIDER_LABEL = [
        'manual' => 'يدوي', 'fake' => 'بيانات عرض (تجريبي)', 'moyasar' => 'ميسر', 'stripe' => 'Stripe',
    ];

    /** أفعال سجل التدقيق بالعربية (المورد.الفعل). */
    private const AUDIT_RESOURCE = [
        'campaign' => 'حملة', 'client' => 'عميل', 'brand' => 'علامة', 'creator' => 'مبدع',
        'content' => 'محتوى', 'contract' => 'عقد', 'payout' => 'مستحق', 'collaboration' => 'تعاون',
        'request' => 'طلب', 'publisher' => 'ناشر', 'subscription' => 'اشتراك', 'tenant' => 'مستأجر',
        'user' => 'مستخدم', 'application' => 'طلب انضمام', 'shortlist' => 'ترشيح', 'document' => 'مستند',
    ];

    private const AUDIT_VERB = [
        'created' => 'أُنشئ', 'updated' => 'حُدّث', 'deleted' => 'حُذف', 'restored' => 'استُعيد',
        'approved' => 'اعتُمد', 'rejected' => 'رُفض', 'submitted' => 'أُرسل', 'active' => 'فُعّل',
        'paused' => 'أُوقف مؤقتًا', 'completed' => 'اكتمل', 'cancelled' => 'أُلغي', 'signed' => 'وُقّع',
        'paid' => 'صُرف', 'converted' => 'حُوّل', 'suspended' => 'عُلّق', 'bypass' => 'تجاوز إداري',
    ];

    /** اسم عربي لنوع الكائن — لا أسماء أصناف في الواجهة. */
    private function subjectLabel(?string $class): string
    {
        $b = $class ? class_basename($class) : null;

        return [
            'Campaign' => 'حملة', 'Client' => 'عميل', 'Brand' => 'علامة', 'Creator' => 'مبدع',
            'ContentItem' => 'محتوى', 'Contract' => 'عقد', 'Payout' => 'مستحق', 'Collaboration' => 'تعاون',
            'ServiceRequest' => 'طلب', 'Publisher' => 'ناشر', 'Subscription' => 'اشتراك', 'Tenant' => 'مستأجر',
            'User' => 'مستخدم', 'CreatorApplication' => 'طلب انضمام', 'ClientDocument' => 'مستند',
        ][$b] ?? ($b ?? '—');
    }

    /** يحوّل «campaign.paused» إلى «حملة · أُوقفت مؤقتًا» بلا مفاتيح خام. */
    private function auditLabel(?string $action): string
    {
        if (! $action) {
            return '—';
        }
        [$res, $verb] = array_pad(explode('.', $action, 2), 2, null);
        $r = self::AUDIT_RESOURCE[$res] ?? $res;
        $v = $verb ? (self::AUDIT_VERB[$verb] ?? $verb) : null;

        return $v ? "$r · $v" : $r;
    }

    public function dashboard(): Response
    {
        [$tenants, $orgs, $users, $activeSubs, $plans, $byStatus, $recent, $audit] = TenantContext::withBypass(function () {
            $tenants = Tenant::count();
            $orgs = Organization::withoutGlobalScopes()->count();
            $users = User::withoutGlobalScopes()->count();
            $activeSubs = Subscription::withoutGlobalScopes()->whereIn('status', ['trialing', 'active'])->count();
            $plans = Plan::where('is_active', true)->count();

            // تسميات عربية من القاموس المركزي — لا تُعرض مفاتيح خام
            $byStatus = Tenant::selectRaw('status, count(*) c')->groupBy('status')->get()
                ->map(fn ($r) => ['status' => $r->status, 'label' => __("statuses.{$r->status}"), 'tone' => __("statuses.tone.{$r->status}"), 'count' => (int) $r->c])
                ->values();
            $recent = Tenant::latest()->limit(8)->get()->map(fn (Tenant $t) => [
                'id' => $t->id, 'name' => $t->name, 'slug' => $t->slug,
                'mode' => $t->deployment_mode, 'status' => $t->status, 'statusLabel' => __("statuses.{$t->status}"), 'statusTone' => __("statuses.tone.{$t->status}"),
                'orgs' => Organization::withoutGlobalScopes()->where('tenant_id', $t->id)->count(),
            ]);
            $audit = AuditLog::withoutGlobalScopes()->latest('occurred_at')->limit(10)->get()->map(fn ($a) => [
                'action' => $this->auditLabel($a->action), 'actor' => $a->actor_name, 'at' => $a->occurred_at?->format('Y-m-d H:i'),
            ]);

            return [$tenants, $orgs, $users, $activeSubs, $plans, $byStatus, $recent, $audit];
        });

        return Inertia::render('Admin/Dashboard', [
            'stats' => ['tenants' => $tenants, 'orgs' => $orgs, 'users' => $users, 'activeSubs' => $activeSubs, 'plans' => $plans],
            'tenantsByStatus' => $byStatus,
            'recentTenants' => $recent,
            'recentAudit' => $audit,
        ]);
    }

    public function tenants(Request $r): Response
    {
        $tenants = TenantContext::withBypass(function () use ($r) {
            $q = Tenant::query();
            if ($s = trim((string) $r->query('q'))) {
                $q->where(fn ($w) => $w->where('name', 'ilike', "%{$s}%")->orWhere('slug', 'ilike', "%{$s}%"));
            }
            if ($st = $r->query('status')) {
                $q->where('status', $st);
            }
            $tenants = $q->latest()->paginate(20)->through(fn (Tenant $t) => [
                'id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'mode' => $t->deployment_mode, 'status' => $t->status, 'statusLabel' => __("statuses.{$t->status}"), 'statusTone' => __("statuses.tone.{$t->status}"),
                'orgs' => Organization::withoutGlobalScopes()->where('tenant_id', $t->id)->count(),
                'members' => OrganizationMembership::withoutGlobalScopes()->where('tenant_id', $t->id)->where('status', 'active')->count(),
                'sub' => Subscription::withoutGlobalScopes()->where('tenant_id', $t->id)->whereIn('status', ['trialing', 'active'])->exists(),
            ]);

            return $tenants;
        });

        return Inertia::render('Admin/Tenants', [
            'tenants' => $tenants,
            'filters' => ['q' => $r->query('q'), 'status' => $r->query('status')],
        ]);
    }

    public function plans(): Response
    {
        $plans = TenantContext::withBypass(fn () => Plan::with(['versions' => fn ($q) => $q->orderBy('version'), 'versions.entitlements'])->orderBy('name')->get()
            ->map(fn (Plan $p) => [
                'id' => $p->id, 'key' => $p->key, 'name' => $p->name, 'active' => (bool) $p->is_active,
                'versions' => $p->versions->map(fn ($v) => [
                    'version' => (int) $v->version, 'active' => (bool) $v->is_active, 'locked' => (bool) $v->is_locked,
                    'entitlements' => $v->entitlements->map(fn ($e) => [
                        'key' => $e->feature_key, 'value' => $e->is_unlimited ? '∞' : (string) $e->value,
                    ])->values(),
                ])->values(),
            ]));

        return Inertia::render('Admin/Plans', ['plans' => $plans]);
    }

    public function subscriptions(Request $r): Response
    {
        $subs = TenantContext::withBypass(function () use ($r) {
            $q = Subscription::withoutGlobalScopes()->with('planVersion.plan');
            if ($st = $r->query('status')) {
                $q->where('status', $st);
            }
            $subs = $q->latest()->paginate(20)->through(fn (Subscription $s) => [
                'id' => $s->id,
                'org' => Organization::withoutGlobalScopes()->where('id', $s->organization_id)->value('name') ?? '—',
                'plan' => $s->planVersion?->plan?->name ?? '—', 'version' => (int) ($s->planVersion?->version ?? 0),
                'status' => $s->status, 'statusLabel' => __("statuses.{$s->status}"), 'statusTone' => __("statuses.tone.{$s->status}"), 'provider' => self::PROVIDER_LABEL[$s->billing_provider] ?? ($s->billing_provider ?? 'يدوي'),
                'trialEndsAt' => $s->trial_ends_at?->format('Y-m-d'),
                'periodEnd' => $s->current_period_end?->format('Y-m-d'),
            ]);

            return $subs;
        });

        return Inertia::render('Admin/Subscriptions', [
            'subs' => $subs,
            'filters' => ['status' => $r->query('status')],
        ]);
    }

    public function audit(Request $r): Response
    {
        $logs = TenantContext::withBypass(fn () => AuditLog::withoutGlobalScopes()->latest('occurred_at')->paginate(40)->through(fn ($a) => [
            'id' => $a->id, 'action' => $this->auditLabel($a->action), 'actor' => $a->actor_name ?? '—',
            'type' => $this->subjectLabel($a->auditable_type), 'auditableId' => $a->auditable_id,
            'tenantId' => $a->tenant_id, 'ip' => $a->ip, 'at' => $a->occurred_at?->format('Y-m-d H:i:s'),
        ]));

        return Inertia::render('Admin/Audit', ['logs' => $logs]);
    }
}
