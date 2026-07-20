<?php

namespace App\Support\Analytics;

use App\Domain\Campaigns\Models\Campaign;
use App\Support\Analytics\FinancialMetrics;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Contracts\Models\Contract;
use App\Domain\CRM\Models\{Brand, Client, ClientContact};
use App\Domain\Finance\Models\Payout;
use App\Domain\Requests\Models\ServiceRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات العملاء — مؤشرات مشتقّة من بيانات PostgreSQL الحقيقية (لا أعمدة مزيّفة).
 *
 * **الأرقام المالية تأتي من `FinancialMetrics` وحده** — إيراد صافٍ من الفواتير
 * المعترَف بها، وتكلفة من المستحقّات الملتزَم بها. كان هذا الملف يعرّفها بنفسه
 * (ميزانيات الحملات وأتعاب التعاونات) فخرجت أرقام لا تصف مالًا وقع.
 *
 * VIP وحالة اكتمال الملف مشتقّة. كل الدوال مُنطّقة تلقائيًا بالمستأجر عبر النطاقات العامة.
 */
class ClientAnalytics
{
    public const VIP_THRESHOLD_MINOR = 30000000; // 300,000 SAR

    /** حقول اكتمال الملف (10 إشارات) */
    private const PROFILE_FIELDS = ['email','phone','website','sector','city',
        'commercial_registration_number','tax_number','account_manager_id'];

    /**
     * مؤشرات مجمّعة لصفحة من العملاء (دفعة واحدة، بلا N+1).
     * @param Collection<int,Client> $clients
     * @return array<int,array> keyed by client id
     */
    public static function forPage(Collection $clients): array
    {
        $ids = $clients->pluck('id')->all();
        if (! $ids) return [];

        // الإيراد من الفواتير المعترَف بها والتكلفة من المستحقّات الملتزَم بها —
        // لا من ميزانيات الحملات وأتعاب التعاونات (خطّة والتزام مُعلَن، لا مال).
        $fin = FinancialMetrics::forClients($ids);
        $active = self::countBy(Campaign::query()->where('status', 'active'), $ids, 'client_id');
        $brands = self::countBy(Brand::query(), $ids, 'client_id');
        $contacts = self::countBy(ClientContact::query(), $ids, 'client_id');
        $overdueReq = self::countBy(ServiceRequest::query()->whereIn('status', ServiceRequest::OPEN_STATUSES)
            ->whereNotNull('sla_breached_at'), $ids, 'client_id');
        $awaitingContent = self::countBy(ContentItem::query()->where('status', 'client_review'), $ids, 'client_id');
        $sentContracts = self::countBy(Contract::query()->where('status', 'sent'), $ids, 'client_id');
        $readyPayouts = self::readyPayoutsByClient($ids);

        $out = [];
        foreach ($clients as $c) {
            $revenue = (int) ($fin[$c->id]['revenue_minor'] ?? 0);
            $costMinor = (int) ($fin[$c->id]['cost_minor'] ?? 0);
            $profit = $revenue - $costMinor;
            $completion = self::completion($c, (int) ($brands[$c->id] ?? 0), (int) ($contacts[$c->id] ?? 0));
            $needsAction = ($overdueReq[$c->id] ?? 0) + ($awaitingContent[$c->id] ?? 0)
                + ($sentContracts[$c->id] ?? 0) + ($readyPayouts[$c->id] ?? 0);
            $out[$c->id] = [
                'revenue_minor' => $revenue,
                'cost_minor' => $costMinor,
                'profit_minor' => $profit,
                'margin' => $revenue > 0 ? round($profit / $revenue * 100) : 0,
                'active_campaigns' => (int) ($active[$c->id] ?? 0),
                'brands' => (int) ($brands[$c->id] ?? 0),
                'completion' => $completion,
                'is_complete' => $completion >= 80,
                'is_vip' => $revenue >= self::VIP_THRESHOLD_MINOR,
                'needs_action' => $needsAction,
                'overdue_requests' => (int) ($overdueReq[$c->id] ?? 0),
                'awaiting_content' => (int) ($awaitingContent[$c->id] ?? 0),
                'sent_contracts' => (int) ($sentContracts[$c->id] ?? 0),
                'ready_payouts' => (int) ($readyPayouts[$c->id] ?? 0),
            ];
        }
        return $out;
    }

    /** ملخّص تصنيفات على مستوى المستأجر (عدّادات حقيقية). */
    public static function summary(): array
    {
        $base = fn () => Client::query();
        return [
            'total' => $base()->count(),
            'active' => $base()->where('status', 'active')->count(),
            'qualified' => $base()->where('status', 'qualified')->count(),
            'lead' => $base()->where('status', 'lead')->count(),
            'inactive' => $base()->whereIn('status', ['inactive', 'suspended'])->count(),
            'complete' => $base()->whereRaw(self::completionCountSql() . ' >= 8')->count(),
            'incomplete' => $base()->whereRaw(self::completionCountSql() . ' < 8')->count(),
            'vip' => $base()->whereRaw(self::revenueSubSql() . ' >= ?', [self::VIP_THRESHOLD_MINOR])->count(),
            'needs_action' => self::needsActionClientCount(),
            'with_active_campaigns' => $base()->whereExists(fn ($q) => $q->select(DB::raw(1))->from('campaigns')
                ->whereColumn('campaigns.client_id', 'clients.id')->where('campaigns.status', 'active'))->count(),
        ];
    }

    /** مؤشرات تشغيلية للوكالة (رأس صفحة العملاء). */
    public static function operational(): array
    {
        // مصدر واحد للأرقام المالية — انظر FinancialMetrics لتعريف كل حدّ
        $fin = FinancialMetrics::agency();
        $revenue = $fin['revenue_minor'];
        $cost = $fin['cost_minor'];
        $activeCampaigns = Campaign::query()->where('status', 'active')->count();
        $pendingPayouts = Payout::query()->whereIn('status', Payout::OPEN)->count();
        $avgCompletion = 0;
        // تُعاد المقاييس مع النتيجة ليستعملها المستدعي بدل إعادة حسابها
        $clients = Client::query()->get();
        $m = [];
        if ($clients->isNotEmpty()) {
            $m = self::forPage($clients);
            $avgCompletion = (int) round(collect($m)->avg('completion'));
        }
        return $fin + [
            'active_campaigns' => $activeCampaigns,
            'pending_payouts' => $pendingPayouts,
            'avg_completion' => $avgCompletion,
            'clients' => $clients,
            'client_metrics' => $m,
        ];
    }

    /** يطبّق تصنيفًا على استعلام العملاء (نفس منطق summary). */
    public static function applySegment($query, ?string $seg)
    {
        return match ($seg) {
            'active' => $query->where('status', 'active'),
            'inactive' => $query->whereIn('status', ['inactive', 'suspended']),
            'complete' => $query->whereRaw(self::completionCountSql() . ' >= 8'),
            'incomplete' => $query->whereRaw(self::completionCountSql() . ' < 8'),
            'vip' => $query->whereRaw(self::revenueSubSql() . ' >= ?', [self::VIP_THRESHOLD_MINOR]),
            'with_active_campaigns' => $query->whereExists(fn ($q) => $q->select(DB::raw(1))->from('campaigns')
                ->whereColumn('campaigns.client_id', 'clients.id')->where('campaigns.status', 'active')),
            'needs_action' => $query->where(function ($q) {
                $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('service_requests')
                    ->whereColumn('service_requests.client_id', 'clients.id')
                    ->whereIn('service_requests.status', ServiceRequest::OPEN_STATUSES)
                    ->whereNotNull('service_requests.sla_breached_at'))
                  ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('content_items')
                    ->whereColumn('content_items.client_id', 'clients.id')
                    ->where('content_items.status', 'client_review'))
                  ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('contracts')
                    ->whereColumn('contracts.client_id', 'clients.id')
                    ->where('contracts.status', 'sent'));
            }),
            default => $query,
        };
    }

    /** تعبير SQL يحسب عدد إشارات اكتمال الملف (0..10) للاستخدام في الفلترة. */
    private static function completionCountSql(): string
    {
        $parts = [];
        foreach (self::PROFILE_FIELDS as $f) {
            $isFk = $f === 'account_manager_id';
            $parts[] = $isFk
                ? "(case when clients.$f is not null then 1 else 0 end)"
                : "(case when clients.$f is not null and clients.$f <> '' then 1 else 0 end)";
        }
        $parts[] = '(case when exists(select 1 from brands where brands.client_id = clients.id and brands.deleted_at is null) then 1 else 0 end)';
        $parts[] = '(case when exists(select 1 from client_contacts where client_contacts.client_id = clients.id and client_contacts.deleted_at is null) then 1 else 0 end)';
        return '(' . implode(' + ', $parts) . ')';
    }

    /** مؤشرات مفصّلة لعميل واحد (لصفحة التفاصيل). */
    public static function forClient(Client $client): array
    {
        $id = $client->id;
        $revenue = (int) Campaign::query()->where('client_id', $id)->sum('budget_minor');
        $cost = (int) Collaboration::query()->where('client_id', $id)->sum('fee_minor');
        $profit = $revenue - $cost;
        $brands = Brand::query()->where('client_id', $id)->count();
        $contacts = ClientContact::query()->where('client_id', $id)->count();
        // المستحق من العميل: قيمة عقود العميل غير المغلقة (تعاقد لم يُنجَز بعد)
        $receivable = (int) Contract::query()->where('client_id', $id)->where('party_type', 'client')
            ->whereNotIn('status', ['completed', 'cancelled', 'terminated'])->sum('value_minor');
        return [
            'revenue_minor' => $revenue,
            'cost_minor' => $cost,
            'profit_minor' => $profit,
            'margin' => $revenue > 0 ? round($profit / $revenue * 100) : 0,
            'campaigns' => Campaign::query()->where('client_id', $id)->count(),
            'active_campaigns' => Campaign::query()->where('client_id', $id)->where('status', 'active')->count(),
            'creators' => Collaboration::query()->where('client_id', $id)->distinct('creator_id')->count('creator_id'),
            'receivable_minor' => $receivable,
            'pending_payouts' => Payout::query()->whereIn('payouts.status', Payout::OPEN)
                ->join('campaigns', 'payouts.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.client_id', $id)->count(),
            'completion' => self::completion($client, $brands, $contacts),
            'is_vip' => $revenue >= self::VIP_THRESHOLD_MINOR,
        ];
    }

    // -------- per-client completion --------
    public static function completion(Client $c, int $brandCount, int $contactCount): int
    {
        $total = count(self::PROFILE_FIELDS) + 2; // + brand + contact
        $filled = 0;
        foreach (self::PROFILE_FIELDS as $f) {
            if (! empty($c->{$f})) $filled++;
        }
        if ($brandCount > 0) $filled++;
        if ($contactCount > 0) $filled++;
        return (int) round($filled / $total * 100);
    }

    // -------- helpers --------
    private static function sumBy($query, array $ids, string $key, string $col): array
    {
        return $query->whereIn($key, $ids)->groupBy($key)
            ->selectRaw("$key as k, sum($col) as v")->pluck('v', 'k')->all();
    }

    private static function countBy($query, array $ids, string $key): array
    {
        return $query->whereIn($key, $ids)->groupBy($key)
            ->selectRaw("$key as k, count(*) as v")->pluck('v', 'k')->all();
    }

    /** مستحقات جاهزة للصرف مجمّعة حسب عميل الحملة (payout -> campaign -> client). */
    private static function readyPayoutsByClient(array $ids): array
    {
        return Payout::query()->whereIn('payouts.status', ['approved', 'scheduled'])
            ->join('campaigns', 'payouts.campaign_id', '=', 'campaigns.id')
            ->whereIn('campaigns.client_id', $ids)
            ->groupBy('campaigns.client_id')
            ->selectRaw('campaigns.client_id as k, count(*) as v')->pluck('v', 'k')->all();
    }

    /**
     * إيراد العميل للتصنيف (VIP) — من الفواتير المعترَف بها لا من الميزانيات.
     * كانت الميزانية تجعل عميلًا بخطّة كبيرة وبلا فاتورة واحدة «VIP».
     */
    private static function revenueSubSql(): string
    {
        $statuses = "'" . implode("','", FinancialMetrics::RECOGNIZED_INVOICE) . "'";

        return "(select coalesce(sum(subtotal_minor - discount_minor),0) from invoices
                 where invoices.client_id = clients.id and invoices.status in ({$statuses}))";
    }

    private static function needsActionClientCount(): int
    {
        return Client::query()->where(function ($q) {
            $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('service_requests')
                ->whereColumn('service_requests.client_id', 'clients.id')
                ->whereIn('service_requests.status', ServiceRequest::OPEN_STATUSES)
                ->whereNotNull('service_requests.sla_breached_at'))
              ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('content_items')
                ->whereColumn('content_items.client_id', 'clients.id')
                ->where('content_items.status', 'client_review'))
              ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('contracts')
                ->whereColumn('contracts.client_id', 'clients.id')
                ->where('contracts.status', 'sent'));
        })->count();
    }
}
