<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * البحث الشامل لمالك المنصّة (§12) — عبر الكيانات الحقيقية في كل المستأجرين. يعمل
 * بـwithBypass (المالك عابر للنطاق)، ولا يبحث في أسرار (آيبان/رموز). كل نتيجة تحمل
 * نوعها ومستأجرها ورابطًا يدخل سياق المستأجر الصحيح (المعاينة العميقة داخل البوّابة
 * في P3). يُرجع JSON لواجهة البحث/لوحة الأوامر في صدفة /platform.
 */
class PlatformSearchController extends Controller
{
    private const PER_TYPE = 6;

    public function __invoke(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['query' => $q, 'results' => []]);
        }
        $like = '%' . $q . '%';

        $results = TenantContext::withBypass(function () use ($q, $like) {
            $out = [];
            $tenantName = fn (?int $tid) => $tid
                ? (\App\Domain\Tenancy\Models\Tenant::find($tid)?->name ?? '—')
                : '—';
            $href = fn (?int $tid) => $tid ? "/platform/tenants/{$tid}" : '';

            // مستأجرون
            foreach (\App\Domain\Tenancy\Models\Tenant::query()
                ->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('slug', 'ilike', $like))
                ->limit(self::PER_TYPE)->get() as $t) {
                $out[] = ['type' => 'tenant', 'typeLabel' => 'مستأجر', 'id' => $t->id, 'name' => $t->name,
                    'sub' => $t->slug, 'tenantId' => $t->id, 'tenant' => $t->name, 'status' => $t->status, 'href' => "/platform/tenants/{$t->id}"];
            }
            // مؤسسات
            foreach (\App\Domain\Tenancy\Models\Organization::withoutGlobalScopes()
                ->where('name', 'ilike', $like)->limit(self::PER_TYPE)->get() as $o) {
                $out[] = ['type' => 'organization', 'typeLabel' => 'مؤسسة', 'id' => $o->id, 'name' => $o->name,
                    'sub' => $o->type, 'tenantId' => $o->tenant_id, 'tenant' => $tenantName($o->tenant_id), 'status' => $o->status, 'href' => $href($o->tenant_id)];
            }
            // مستخدمون (اسم/بريد) — بلا كلمات مرور أو أسرار
            foreach (\App\Domain\Identity\Models\User::withoutGlobalScopes()
                ->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))
                ->limit(self::PER_TYPE)->get() as $u) {
                $tid = \App\Domain\Tenancy\Models\OrganizationMembership::withoutGlobalScopes()->where('user_id', $u->id)->value('tenant_id');
                $out[] = ['type' => 'user', 'typeLabel' => 'مستخدم', 'id' => $u->id, 'name' => $u->name,
                    'sub' => $u->email, 'tenantId' => $tid, 'tenant' => $tenantName($tid), 'status' => $u->is_active ? 'active' : 'inactive', 'href' => $href($tid)];
            }
            // عملاء / علامات / حملات / صناع محتوى / عقود / فواتير / مستحقات
            $this->collect($out, \App\Domain\CRM\Models\Client::class, 'client', 'عميل',
                fn ($w) => $w->where('display_name', 'ilike', $like), fn ($x) => [$x->display_name, null], $tenantName, $href);
            $this->collect($out, \App\Domain\CRM\Models\Brand::class, 'brand', 'علامة',
                fn ($w) => $w->where('name', 'ilike', $like), fn ($x) => [$x->name, null], $tenantName, $href);
            $this->collect($out, \App\Domain\Campaigns\Models\Campaign::class, 'campaign', 'حملة',
                fn ($w) => $w->where('name', 'ilike', $like)->orWhere('campaign_number', 'ilike', $like), fn ($x) => [$x->name, $x->campaign_number], $tenantName, $href);
            $this->collect($out, \App\Domain\Creators\Models\Creator::class, 'creator', 'صانع محتوى',
                fn ($w) => $w->where('display_name', 'ilike', $like)->orWhere('creator_number', 'ilike', $like), fn ($x) => [$x->display_name, $x->creator_number], $tenantName, $href);
            $this->collect($out, \App\Domain\Contracts\Models\Contract::class, 'contract', 'عقد',
                fn ($w) => $w->where('contract_number', 'ilike', $like)->orWhere('title', 'ilike', $like), fn ($x) => [$x->title ?: $x->contract_number, $x->contract_number], $tenantName, $href);
            $this->collect($out, \App\Domain\Finance\Models\Invoice::class, 'invoice', 'فاتورة',
                fn ($w) => $w->where('invoice_number', 'ilike', $like), fn ($x) => [$x->invoice_number, null], $tenantName, $href);
            $this->collect($out, \App\Domain\Finance\Models\Payout::class, 'payout', 'مستحق',
                fn ($w) => $w->where('payout_number', 'ilike', $like), fn ($x) => [$x->payout_number, null], $tenantName, $href);

            return $out;
        });

        \App\Domain\Audit\Services\AuditLogger::log('platform.search', null, ['q' => $q, 'count' => count($results)], null, $r->user()?->id);

        return response()->json(['query' => $q, 'results' => $results]);
    }

    /** يجمع نتائج نوع كيان (BelongsToTenant) بأمان: بلا نطاق، بحدّ، مع رابط سياق المستأجر. */
    private function collect(array &$out, string $model, string $type, string $label, callable $where, callable $display, callable $tenantName, callable $href): void
    {
        foreach ($model::withoutGlobalScopes()->where($where)->limit(self::PER_TYPE)->get() as $row) {
            [$name, $sub] = $display($row);
            $out[] = ['type' => $type, 'typeLabel' => $label, 'id' => $row->id, 'name' => $name ?: ('#' . $row->id),
                'sub' => $sub, 'tenantId' => $row->tenant_id, 'tenant' => $tenantName($row->tenant_id),
                'status' => $row->status ?? null, 'href' => $href($row->tenant_id)];
        }
    }
}
