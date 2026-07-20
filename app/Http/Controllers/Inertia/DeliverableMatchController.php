<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable, CampaignShortlist};
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Collaborations\Services\CollaborationWorkflowService;
use App\Domain\Creators\Models\Creator;
use App\Domain\Collaborations\Services\CreatorMatchingService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مطابقة المبدعين لمخرَج حملة وعرض التعاون عليهم (React/Inertia).
 *
 * منقول من CollaborationController (Blade) بنفس CreatorMatchingService
 * وCollaborationWorkflowService. الدرجة والأسباب تأتي من المطابِق كما هي —
 * لا ترتيب ولا أرقام تُلفَّق في الواجهة.
 */
class DeliverableMatchController extends Controller
{
    public function suggest(Request $r, Campaign $campaign, int $deliverable, CreatorMatchingService $matcher): Response
    {
        $this->authorize('view', $campaign);
        $d = $this->deliverableOf($campaign, $deliverable);

        // المطابِق يفتح سياق المخرَج ويستعيد ما كان بعده — فلا تعويض هنا
        $matches = $matcher->suggestForDeliverable($d);

        $approved = $this->clientApprovedCreatorIds($campaign);

        $suggestions = $matches->map(fn (array $s) => [
            'creatorId' => $s['creator']->id,
            'name' => $s['creator']->display_name,
            'handle' => $s['creator']->handle,
            'platform' => $s['creator']->primary_platform,
            'followers' => (int) ($s['creator']->followers_count ?? 0),
            'score' => (int) $s['score'],
            'reasons' => $s['reasons'],
            // عُرض عليه هذا المخرَج من قبل — الخادم يمنع التكرار، وهذه إشارته في الواجهة
            'alreadyOffered' => $this->hasCollaboration($d->id, $s['creator']->id),
            // اعتمده العميل في الترشيح — هو سبب وجود الوكالة على هذه الشاشة
            'clientApproved' => $approved->contains($s['creator']->id),
        ])
            // المعتمَد من العميل أوّلًا: الإشعار يقول «أنشئ التعاون مع المعتمَدين»
            ->sortByDesc('clientApproved')
            ->values();

        return Inertia::render('Campaigns/Suggest', [
            'campaign' => ['id' => $campaign->id, 'name' => $campaign->name],
            'deliverable' => [
                'id' => $d->id, 'type' => $d->type, 'platform' => $d->platform, 'quantity' => (int) $d->quantity,
            ],
            'suggestions' => $suggestions,
            'canOffer' => $r->user()->can('create', Collaboration::class),
        ]);
    }

    public function offer(Request $r, Campaign $campaign, int $deliverable, CollaborationWorkflowService $wf): RedirectResponse
    {
        $this->authorize('create', Collaboration::class);
        $data = $r->validate(['creator_id' => 'required|integer']);
        $d = $this->deliverableOf($campaign, $deliverable);
        Creator::findOrFail($data['creator_id']); // ضمن المستأجر

        try {
            $c = $wf->offerFromDeliverable($d, (int) $data['creator_id'], $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

        return redirect(MountPrefix::path($r, "/collaborations/{$c->id}"))->with('ok', 'أُرسل عرض التعاون.');
    }

    /**
     * المخرَج يجب أن يخصّ هذه الحملة — يمنع الوصول لمخرَج حملة أخرى بتبديل الرقم.
     *
     * الحملة وصلت عبر ربط مسار مُقيَّد بالمستأجر، فالسياق الحالي هو مستأجرها.
     * لا نستدعي reset() هنا: ذلك يمسح سياق الطلب الذي ضبطه وسيط المستأجر
     * فتفقد الاستعلامات التالية في نفس الطلب نطاقها.
     */
    private function deliverableOf(Campaign $campaign, int $deliverable): CampaignDeliverable
    {
        $d = CampaignDeliverable::where('id', $deliverable)->where('campaign_id', $campaign->id)->first();
        abort_unless($d, 404);

        return $d;
    }

    /**
     * من اعتمدهم العميل في آخر إصدار ترشيح مُرسَل.
     *
     * الوكالة تصل هذه الشاشة من إشعار «أنشئ التعاون مع المعتمَدين»، وكانت تجد
     * قائمة مطابقة عامة لا تُميّز من اعتمده العميل ممّن لم يُعرض عليه أصلًا —
     * فينقطع أثر القرار عند الخطوة التي وُجد من أجلها.
     */
    private function clientApprovedCreatorIds(Campaign $campaign): \Illuminate\Support\Collection
    {
        $version = CampaignShortlist::where('campaign_id', $campaign->id)->first()
            ?->versions()->where('status', '!=', 'draft')->orderByDesc('version')->first();

        return $version
            ? $version->items()->where('client_decision', 'approved')->pluck('creator_id')
            : collect();
    }

    /**
     * بالمخرَج لا بالحملة: المبدع متعدّد القدرات قد يأخذ منشورًا وUGC في الحملة
     * نفسها. الوسم بالحملة كان يُخفي مرشّحًا مشروعًا عن المخرَج الثاني.
     * التعاون المنتهي (اعتذار/إلغاء) لا يشغل المخرَج فلا يُوسَم.
     */
    private function hasCollaboration(int $deliverableId, int $creatorId): bool
    {
        return Collaboration::where('deliverable_id', $deliverableId)
            ->where('creator_id', $creatorId)
            ->whereNotIn('status', ['declined', 'cancelled'])
            ->exists();
    }
}
