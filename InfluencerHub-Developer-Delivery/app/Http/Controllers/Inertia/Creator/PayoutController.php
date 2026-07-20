<?php

namespace App\Http\Controllers\Inertia\Creator;

use App\Domain\Finance\Models\Payout;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مستحقات المبدع (React/Inertia) — عرض فقط، معزول على المبدع النشِط.
 * لا إجراءات مالية من هنا؛ الأرقام فعلية من قاعدة البيانات.
 */
class PayoutController extends Controller
{
    public function index(Request $r): Response
    {
        $c = $r->attributes->get('creator');
        $items = Payout::with('campaign')->where('creator_id', $c->id)->latest()->paginate(15)
            ->through(fn (Payout $p) => [
                'id' => $p->id, 'number' => $p->payout_number, 'description' => $p->description,
                'campaign' => $p->campaign?->name,
                'amountMinor' => (int) $p->amount_minor, 'currency' => $p->currency,
                'status' => $p->status, 'statusLabel' => __("statuses.{$p->status}"), 'statusTone' => __("statuses.tone.{$p->status}"),
                'dueDate' => $p->due_date?->format('Y-m-d'), 'paidAt' => $p->paid_at?->format('Y-m-d'),
                'reference' => $p->payment_reference,
            ]);
        $paidMinor = (int) Payout::where('creator_id', $c->id)->where('status', 'paid')->sum('amount_minor');
        $openMinor = (int) Payout::where('creator_id', $c->id)->whereIn('status', Payout::OPEN)->sum('amount_minor');
        $currency = Payout::where('creator_id', $c->id)->value('currency') ?? 'SAR';

        return Inertia::render('CreatorPortal/Payouts/Index', [
            'creatorName' => $c->display_name,
            'items' => $items,
            'paidMinor' => $paidMinor,
            'openMinor' => $openMinor,
            'currency' => $currency,
        ]);
    }
}
