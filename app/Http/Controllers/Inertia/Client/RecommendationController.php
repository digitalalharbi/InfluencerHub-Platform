<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\AdminPool\Models\{PoolCreator, PoolRecommendation};
use App\Http\Controllers\Controller;
use Illuminate\Http\{RedirectResponse, Request};
use Inertia\{Inertia, Response};

/**
 * ترشيحات المؤثرين في بوابة العميل — يقرّ العميل (قبول/رفض) ما حوّله مدير النظام.
 *
 * معزولة على العميل النشِط (EnsureClientMember) ونطاق المستأجر. نسخة مستقلّة عن
 * القاعدة وبلا جوّال — العميل يرى الاسم والمنصّة والمتابعين والسعر المعروض فقط.
 */
class RecommendationController extends Controller
{
    public function index(Request $r): Response
    {
        /** @var \App\Domain\CRM\Models\Client $c */
        $c = $r->attributes->get('activeClient');

        $base = PoolRecommendation::where('client_id', $c->id);
        $counts = [
            'pending' => (clone $base)->where('status', 'recommended')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'total' => (clone $base)->count(),
        ];

        // المعلّق أولًا، ثم الأحدث
        $items = (clone $base)
            ->orderByRaw("case status when 'recommended' then 0 when 'approved' then 1 else 2 end")
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (PoolRecommendation $x) => [
                'id' => $x->id,
                'name' => $x->name,
                'platformLabel' => PoolCreator::PLATFORM_LABELS[$x->platform] ?? $x->platform,
                'accountUrl' => $x->account_url,
                'followers' => $x->followers,
                'categories' => $x->categories ?? [],
                'priceMinor' => $x->price_minor,
                'region' => $x->region,
                'city' => $x->city,
                'sourceType' => $x->source_type,
                'status' => $x->status,
                'reason' => $x->decision_reason,
                'decidedAt' => $x->decided_at?->format('Y-m-d'),
            ]);

        return Inertia::render('ClientPortal/Recommendations/Index', [
            'clientName' => $c->display_name,
            'items' => $items,
            'counts' => $counts,
        ]);
    }

    public function decision(Request $r, int $recommendation): RedirectResponse
    {
        /** @var \App\Domain\CRM\Models\Client $c */
        $c = $r->attributes->get('activeClient');
        $data = $r->validate([
            'decision' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|max:500',
        ], [], ['decision' => 'القرار', 'reason' => 'السبب']);

        // ضمن نطاق العميل النشِط فقط
        $rec = PoolRecommendation::where('client_id', $c->id)->whereKey($recommendation)->first();
        abort_unless($rec, 404);

        $rec->update([
            'status' => $data['decision'],
            'decision_reason' => $data['reason'] ?? null,
            'decided_at' => now(),
        ]);

        return back()->with('ok', $data['decision'] === 'approved' ? 'اعتمدت المؤثر المرشّح.' : 'رفضت المؤثر المرشّح.');
    }
}
