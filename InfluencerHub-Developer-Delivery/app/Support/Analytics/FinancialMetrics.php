<?php

namespace App\Support\Analytics;

use App\Domain\Finance\Models\{Invoice, InvoicePayment, Payout};
use Illuminate\Support\Facades\DB;

/**
 * تعريف واحد للأرقام المالية — الحملة والعميل والتقارير واللوحة والتصدير.
 *
 * كانت الأرقام تُحسب في موضعين بتعريفين خاطئين:
 * - «الإيراد» = مجموع **ميزانيات** الحملات. والميزانية خطّة لا مالًا مكتسَبًا:
 *   حملة بميزانية مليون بلا فاتورة كانت تُظهر إيرادًا مليونًا.
 * - «التكلفة» = مجموع **أتعاب التعاونات** بما فيها الملغاة، وهي التزام مُعلَن
 *   لا مالًا خرج. فالتعاون الملغى كان يُحمَّل تكلفةً.
 * فخرج الربح والهامش من طرفين خاطئين معًا.
 *
 * القواعد هنا:
 * - **الإيراد صافٍ من الضريبة.** ضريبة القيمة المضافة تُحصَّل لحساب الدولة
 *   لا لحساب الوكالة، فإدخالها في الإيراد يضخّمه ويكسر الهامش.
 * - **المعترَف به ما صدر.** المسودة نيّة والملغاة لا شيء.
 * - **التكلفة ما التُزم به فعلًا** من المستحقّات، لا ما أُلغي أو فشل.
 * - **التحصيل مقياس نقدي مستقلّ** عن الإيراد، ولا يُخلَط به.
 *
 * كل المبالغ minor units (هللات). لا قسمة على 100 هنا — العرض وحده يقسم.
 */
final class FinancialMetrics
{
    /** فواتير تُحتسب في الإيراد: صدرت فعلًا. */
    public const RECOGNIZED_INVOICE = ['issued', 'partially_paid', 'overdue', 'paid'];

    /** مستحقّات تُحتسب تكلفةً: التزام قائم أو مدفوع. الملغى والفاشل ليسا تكلفة. */
    public const COMMITTED_PAYOUT = ['pending', 'approved', 'scheduled', 'waiting_for_provider', 'paid'];

    /**
     * المقاييس المالية ضمن نطاق اختياري.
     *
     * @param  callable|null  $scopeInvoice  يقيّد استعلام الفواتير
     * @param  callable|null  $scopePayout   يقيّد استعلام المستحقّات
     * @return array<string,int|float>
     */
    public static function compute(?callable $scopeInvoice = null, ?callable $scopePayout = null): array
    {
        $inv = Invoice::query()->whereIn('status', self::RECOGNIZED_INVOICE);
        if ($scopeInvoice) {
            $scopeInvoice($inv);
        }

        $row = (clone $inv)->selectRaw(
            'coalesce(sum(subtotal_minor),0) sub, coalesce(sum(discount_minor),0) disc,
             coalesce(sum(tax_minor),0) tax, coalesce(sum(total_minor),0) tot'
        )->first();

        // الإيراد صافٍ: بعد الخصم وقبل الضريبة
        $revenue = (int) $row->sub - (int) $row->disc;
        $tax = (int) $row->tax;
        $billed = (int) $row->tot;

        $collected = (int) InvoicePayment::query()
            ->whereIn('invoice_id', (clone $inv)->select('id'))
            ->sum('amount_minor');

        $pay = Payout::query()->whereIn('status', self::COMMITTED_PAYOUT);
        if ($scopePayout) {
            $scopePayout($pay);
        }
        $cost = (int) (clone $pay)->sum('amount_minor');
        $paidOut = (int) (clone $pay)->where('status', 'paid')->sum('amount_minor');

        $profit = $revenue - $cost;

        return [
            'revenue_minor' => $revenue,          // صافي الإيراد (بلا ضريبة)
            'tax_minor' => $tax,                  // ضريبة محصَّلة لحساب الدولة
            'billed_minor' => $billed,            // إجمالي المفوتَر (بالضريبة)
            'collected_minor' => $collected,      // نقدًا وصل
            'outstanding_minor' => $billed - $collected,
            'cost_minor' => $cost,                // تكلفة صناع المحتوى الملتزَم بها
            'cost_paid_minor' => $paidOut,        // منها ما صُرف فعلًا
            'profit_minor' => $profit,            // = revenue - cost
            'margin' => self::margin($profit, $revenue),
        ];
    }

    /** الهامش نسبة مئوية بخانة عشرية واحدة. إيراد صفر ⇒ صفر لا قسمة على صفر. */
    public static function margin(int $profitMinor, int $revenueMinor): float
    {
        return $revenueMinor > 0 ? round($profitMinor / $revenueMinor * 100, 1) : 0.0;
    }

    /** مقاييس الوكالة كاملةً (ضمن سياق المستأجر الحالي). */
    public static function agency(): array
    {
        return self::compute();
    }

    /** مقاييس حملة واحدة. */
    public static function campaign(int $campaignId): array
    {
        return self::compute(
            fn ($q) => $q->where('campaign_id', $campaignId),
            fn ($q) => $q->where('campaign_id', $campaignId),
        );
    }

    /**
     * مقاييس عميل واحد.
     *
     * المستحقّ لا يحمل `client_id` — يُنسَب إلى العميل عبر حملته، وما لا حملة
     * له لا يُنسَب إلى عميل فلا يُحتسب في تكلفته.
     */
    public static function client(int $clientId): array
    {
        return self::compute(
            fn ($q) => $q->where('client_id', $clientId),
            fn ($q) => $q->whereIn('campaign_id', DB::table('campaigns')->where('client_id', $clientId)->select('id')),
        );
    }

    /**
     * إيراد وتكلفة لكل عميل دفعةً — يتجنّب N+1 في صفحة العملاء.
     *
     * @param  array<int>  $clientIds
     * @return array<int,array<string,int|float>>
     */
    public static function forClients(array $clientIds): array
    {
        if (! $clientIds) {
            return [];
        }

        $rev = Invoice::query()->whereIn('status', self::RECOGNIZED_INVOICE)
            ->whereIn('client_id', $clientIds)
            ->groupBy('client_id')
            ->selectRaw('client_id, coalesce(sum(subtotal_minor - discount_minor),0) revenue, coalesce(sum(total_minor),0) billed')
            ->get()->keyBy('client_id');

        // تكلفة المستحقّات منسوبة عبر الحملة إلى عميلها
        $cost = Payout::query()->whereIn('payouts.status', self::COMMITTED_PAYOUT)
            ->join('campaigns', 'campaigns.id', '=', 'payouts.campaign_id')
            ->whereIn('campaigns.client_id', $clientIds)
            ->groupBy('campaigns.client_id')
            ->selectRaw('campaigns.client_id as cid, coalesce(sum(payouts.amount_minor),0) cost')
            ->get()->keyBy('cid');

        $out = [];
        foreach ($clientIds as $id) {
            $revenue = (int) ($rev[$id]->revenue ?? 0);
            $c = (int) ($cost[$id]->cost ?? 0);
            $out[$id] = [
                'revenue_minor' => $revenue,
                'billed_minor' => (int) ($rev[$id]->billed ?? 0),
                'cost_minor' => $c,
                'profit_minor' => $revenue - $c,
                'margin' => self::margin($revenue - $c, $revenue),
            ];
        }

        return $out;
    }
}
