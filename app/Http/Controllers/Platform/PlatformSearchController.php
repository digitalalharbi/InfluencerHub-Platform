<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Services\PlatformPortalEligibilityService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * البحث الشامل لمالك المنصّة (§12) — عبر الكيانات الحقيقية في كل المستأجرين (withBypass،
 * لا أسرار). كل نتيجة تحمل: entityType، entityId، tenantId، organizationId (إن وُجد)،
 * portalHint، contextHref. سياق المستخدم يُحسب من **كل** علاقات البوّابة (لا أول عضوية
 * فقط) عبر PlatformPortalEligibilityService. contextHref = /platform/tenants/{tenant}
 * مؤقّتًا حتى توفّر P3 وجهات المعاينة العميقة. التدقيق يسجّل طول الاستعلام وعدد النتائج
 * والأنواع فقط — لا نصّ البحث الخام (§4 hardening).
 */
class PlatformSearchController extends Controller
{
    private const PER_TYPE = 6;

    public function __construct(private PlatformPortalEligibilityService $eligibility)
    {
    }

    public function __invoke(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['query' => $q, 'results' => []]);
        }
        $like = '%' . $q . '%';

        $results = TenantContext::withBypass(function () use ($like) {
            $out = [];
            $tenantName = fn (?int $tid) => $tid ? (\App\Domain\Tenancy\Models\Tenant::find($tid)?->name ?? '—') : '—';
            $push = function (string $entityType, string $label, int $entityId, ?string $name, ?string $sub, ?int $tenantId, ?int $orgId, ?string $portalHint, ?string $status) use (&$out, $tenantName) {
                $out[] = [
                    'entityType' => $entityType, 'typeLabel' => $label, 'entityId' => $entityId,
                    'name' => $name ?: ('#' . $entityId), 'sub' => $sub,
                    'tenantId' => $tenantId, 'tenant' => $tenantId ? $tenantName($tenantId) : '—',
                    'organizationId' => $orgId, 'portalHint' => $portalHint, 'status' => $status,
                    'contextHref' => $tenantId ? "/platform/tenants/{$tenantId}" : '',
                ];
            };

            foreach (\App\Domain\Tenancy\Models\Tenant::query()
                ->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('slug', 'ilike', $like))->limit(self::PER_TYPE)->get() as $t) {
                $push('tenant', 'مستأجر', (int) $t->id, $t->name, $t->slug, (int) $t->id, null, null, $t->status);
            }
            foreach (\App\Domain\Tenancy\Models\Organization::withoutGlobalScopes()->where('name', 'ilike', $like)->limit(self::PER_TYPE)->get() as $o) {
                $push('organization', 'مؤسسة', (int) $o->id, $o->name, $o->type, (int) $o->tenant_id, (int) $o->id, 'agency', $o->status);
            }
            // مستخدمون — سياق متعدّد من كل علاقات البوّابة (لا أول عضوية فقط).
            foreach (\App\Domain\Identity\Models\User::withoutGlobalScopes()
                ->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))->limit(self::PER_TYPE)->get() as $u) {
                $status = $u->is_active ? 'active' : 'inactive';
                $contexts = $this->eligibility->contextsForUser($u);
                if ($contexts === []) {
                    $push('user', 'مستخدم', (int) $u->id, $u->name, $u->email, null, null, null, $status);
                } else {
                    // كل السياقات المؤهَّلة — بلا اقتطاع (§1). حماية الحجم تكون بحدّ
                    // المستخدمين المطابقين (PER_TYPE) لا بإسقاط سياقات صامتًا.
                    foreach ($contexts as $c) {
                        $push('user', 'مستخدم', (int) $u->id, $u->name, $u->email, $c['tenantId'], $c['organizationId'], $c['portal'], $status);
                    }
                }
            }

            // كيانات يديرها المستأجر (portalHint=agency؛ contextHref لمستأجرها).
            $this->collect($push, \App\Domain\CRM\Models\Client::class, 'client', 'عميل',
                fn ($w) => $w->where('display_name', 'ilike', $like), fn ($x) => [$x->display_name, null]);
            $this->collect($push, \App\Domain\CRM\Models\Brand::class, 'brand', 'علامة',
                fn ($w) => $w->where('name', 'ilike', $like), fn ($x) => [$x->name, null]);
            $this->collect($push, \App\Domain\Campaigns\Models\Campaign::class, 'campaign', 'حملة',
                fn ($w) => $w->where('name', 'ilike', $like)->orWhere('campaign_number', 'ilike', $like), fn ($x) => [$x->name, $x->campaign_number]);
            $this->collect($push, \App\Domain\Creators\Models\Creator::class, 'creator', 'صانع محتوى',
                fn ($w) => $w->where('display_name', 'ilike', $like)->orWhere('creator_number', 'ilike', $like), fn ($x) => [$x->display_name, $x->creator_number]);
            $this->collect($push, \App\Domain\Contracts\Models\Contract::class, 'contract', 'عقد',
                fn ($w) => $w->where('contract_number', 'ilike', $like)->orWhere('title', 'ilike', $like), fn ($x) => [$x->title ?: $x->contract_number, $x->contract_number]);
            $this->collect($push, \App\Domain\Finance\Models\Invoice::class, 'invoice', 'فاتورة',
                fn ($w) => $w->where('invoice_number', 'ilike', $like), fn ($x) => [$x->invoice_number, null]);
            $this->collect($push, \App\Domain\Finance\Models\Payout::class, 'payout', 'مستحق',
                fn ($w) => $w->where('payout_number', 'ilike', $like), fn ($x) => [$x->payout_number, null]);

            return $out;
        });

        $types = array_values(array_unique(array_map(fn ($r) => $r['entityType'], $results)));
        // §4 hardening: لا نصّ بحث خام في سجلّ دائم — فقط الطول والعدد والأنواع.
        \App\Domain\Audit\Services\AuditLogger::log('platform.search', null,
            ['query_length' => mb_strlen($q), 'result_count' => count($results), 'searched_types' => $types], null, $r->user()?->id);

        return response()->json(['query' => $q, 'results' => $results]);
    }

    /** يجمع نتائج نوع كيان (BelongsToTenant) — portalHint=agency، رابط سياق مستأجره. */
    private function collect(callable $push, string $model, string $type, string $label, callable $where, callable $display): void
    {
        foreach ($model::withoutGlobalScopes()->where($where)->limit(self::PER_TYPE)->get() as $row) {
            [$name, $sub] = $display($row);
            $push($type, $label, (int) $row->id, $name, $sub, $row->tenant_id ? (int) $row->tenant_id : null, null, 'agency', $row->status ?? null);
        }
    }
}
