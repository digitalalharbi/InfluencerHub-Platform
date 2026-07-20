<?php

namespace App\Http\Controllers\Inertia\Creator;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Finance\Models\Payout;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * لوحة بوابة المبدع (React/Inertia) — بيانات فعلية معزولة على المبدع النشِط (EnsureCreator).
 * تُبرز ما يحتاج فعل المبدع: تعاونات فعّالة + محتوى بحاجة تسليم/تعديل + عقود للتوقيع + مستحقات مفتوحة.
 */
class DashboardController extends Controller
{
    public function index(Request $r): Response
    {
        $c = $r->attributes->get('creator');

        $activeCollabs = Collaboration::where('creator_id', $c->id)->whereIn('status', Collaboration::CREATOR_ACTIONABLE)->count();
        $contentTodo = ContentItem::where('creator_id', $c->id)->whereIn('status', ['draft', 'changes_requested'])->count();
        $contractsPending = Contract::where('party_type', 'creator')->where('creator_id', $c->id)->where('status', 'sent')->count();
        $payoutsOpen = Payout::where('creator_id', $c->id)->whereIn('status', Payout::OPEN)->count();

        $paidMinor = (int) Payout::where('creator_id', $c->id)->where('status', 'paid')->sum('amount_minor');
        $openMinor = (int) Payout::where('creator_id', $c->id)->whereIn('status', Payout::OPEN)->sum('amount_minor');
        $followers = (int) $c->platforms()->sum('followers_count');

        $recent = Collaboration::with('campaign', 'client')->where('creator_id', $c->id)->latest()->limit(5)->get()
            ->map(fn (Collaboration $cl) => [
                'id' => $cl->id, 'number' => $cl->collaboration_number,
                'campaign' => $cl->campaign?->name, 'client' => $cl->client?->display_name,
                'status' => $cl->status, 'statusLabel' => __("statuses.{$cl->status}"), 'statusTone' => __("statuses.tone.{$cl->status}"),
                'feeMinor' => (int) ($cl->fee_minor ?? 0),
            ]);


        return Inertia::render('CreatorPortal/Dashboard', [
            'creator' => [
                'name' => $c->display_name, 'handle' => $c->handle, 'platform' => $c->primary_platform,
                'verified' => $c->mowthooq_status === 'verified', 'followers' => $followers,
            ],
            'pending' => [
                ['key' => 'collaborations', 'label' => 'تعاونات نشطة تحتاجك', 'count' => $activeCollabs, 'icon' => 'git-merge', 'link' => '/collaborations'],
                ['key' => 'content', 'label' => 'محتوى بحاجة تسليم/تعديل', 'count' => $contentTodo, 'icon' => 'image', 'link' => '/content'],
                ['key' => 'contracts', 'label' => 'عقود بانتظار توقيعك', 'count' => $contractsPending, 'icon' => 'file-text', 'link' => '/contracts'],
                ['key' => 'payouts', 'label' => 'مستحقات مفتوحة', 'count' => $payoutsOpen, 'icon' => 'wallet', 'link' => '/payouts'],
            ],
            'earnings' => ['paidMinor' => $paidMinor, 'openMinor' => $openMinor],
            'recent' => $recent,
        ]);
    }
}
