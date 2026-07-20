<?php
namespace App\Domain\Campaigns\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Campaigns\Models\{Campaign, CampaignShortlist, CampaignShortlistVersion, CampaignShortlistItem};
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/** محرّك الترشيح — قائمة أساسية/احتياطية بإصدارات + درجة ملاءمة + قرار العميل. */
class ShortlistService
{
    public function __construct(private NotificationService $notifications) {}

    /** ينشئ (أو يجلب) قائمة ترشيح للحملة بإصدار مسودة نشط. */
    public function getOrCreate(Campaign $campaign, ?int $actorId = null): CampaignShortlist
    {
        return DB::transaction(function () use ($campaign, $actorId) {
            $sl = CampaignShortlist::firstOrCreate(
                ['campaign_id' => $campaign->id],
                ['tenant_id' => $campaign->tenant_id, 'status' => 'draft', 'created_by' => $actorId]
            );
            $sl->refresh();
            if ((int) $sl->current_version < 1) {
                $v = CampaignShortlistVersion::create(['tenant_id' => $sl->tenant_id, 'shortlist_id' => $sl->id, 'version' => 1, 'status' => 'draft']);
                $sl->update(['current_version' => 1]);
            }
            return $sl->fresh();
        });
    }

    /** درجة ملاءمة مبدع للحملة (0..100) + أسباب — من بيانات فعلية. */
    public function matchScore(Campaign $campaign, Creator $creator): array
    {
        $reasons = [];
        $score = 0;
        $platforms = $campaign->deliverables->pluck('platform')->filter()->unique();
        if ($platforms->isEmpty() || $platforms->contains($creator->primary_platform)) {
            $score += 40; $reasons[] = 'المنصّة مطابقة';
        }
        $followers = (int) $creator->followers_count;
        if ($followers >= 500000) { $score += 25; $reasons[] = 'وصول واسع'; }
        elseif ($followers >= 100000) { $score += 15; $reasons[] = 'وصول جيد'; }
        else { $score += 8; }
        if ($creator->mowthooq_status === 'verified') { $score += 20; $reasons[] = 'موثّق'; }
        if (! empty($creator->rate_per_post_minor)) { $score += 10; $reasons[] = 'سعر محدّد'; }
        else { $reasons[] = 'سعر غير محدّد'; }
        if ($creator->status === 'active') { $score += 5; }
        return ['score' => min(100, $score), 'reasons' => $reasons];
    }

    public function addCreator(CampaignShortlistVersion $version, Creator $creator, bool $backup = false): CampaignShortlistItem
    {
        $campaign = $version->shortlist->campaign;
        $m = $this->matchScore($campaign, $creator);
        return CampaignShortlistItem::updateOrCreate(
            ['shortlist_version_id' => $version->id, 'creator_id' => $creator->id],
            ['tenant_id' => $version->tenant_id, 'is_backup' => $backup,
             'proposed_fee_minor' => (int) ($creator->rate_per_post_minor ?? 0),
             'match_score' => $m['score'], 'reasons' => $m['reasons']]
        );
    }

    public function removeItem(CampaignShortlistItem $item): void { $item->delete(); }

    /** إرسال للعميل: يقفل الإصدار الحالي كـsubmitted. */
    /**
     * إرسال الإصدار الحالي لاعتماد العميل.
     * كان يعود صامتًا عند غياب المرشّحين، فيضغط المستخدم بلا أثر ولا سبب.
     * الآن يُرفع السبب صراحةً ليصل إلى الواجهة.
     */
    public function submit(CampaignShortlist $sl): void
    {
        $v = $sl->currentVersion();
        if (! $v) {
            throw new \RuntimeException('لا يوجد إصدار ترشيح لهذه الحملة.');
        }
        if ($v->items()->count() === 0) {
            throw new \RuntimeException('أضِف مؤثرًا واحدًا على الأقل قبل الإرسال.');
        }
        $v->update(['status' => 'submitted', 'submitted_at' => now()]);
        $sl->update(['status' => 'submitted']);
    }

    /** إعادة الترشيح: إصدار جديد يَنسخ عناصر الإصدار السابق (تاريخ محفوظ). */
    public function newRevision(CampaignShortlist $sl): CampaignShortlistVersion
    {
        return DB::transaction(function () use ($sl) {
            $prev = $sl->currentVersion();
            $n = $sl->current_version + 1;
            $v = CampaignShortlistVersion::create(['tenant_id' => $sl->tenant_id, 'shortlist_id' => $sl->id, 'version' => $n, 'status' => 'draft']);
            if ($prev) {
                foreach ($prev->items as $it) {
                    CampaignShortlistItem::create(['tenant_id' => $sl->tenant_id, 'shortlist_version_id' => $v->id,
                        'creator_id' => $it->creator_id, 'is_backup' => $it->is_backup, 'proposed_fee_minor' => $it->proposed_fee_minor,
                        'match_score' => $it->match_score, 'reasons' => $it->reasons]);
                }
            }
            $sl->update(['current_version' => $n, 'status' => 'draft']);
            return $v;
        });
    }

    public function clientDecision(CampaignShortlistItem $item, string $decision, ?string $reason = null): void
    {
        $item->update(['client_decision' => $decision, 'decision_reason' => $reason]);
        $v = $item->version;
        $all = $v->items()->get();
        $total = $all->count();
        $approved = $all->where('client_decision', 'approved')->count();
        $pending = $all->where('client_decision', 'pending')->count();
        // كل الحالات محسومة: اعتماد كامل / رفض كامل / مزيج. مع بقاء معلّقات: قيد المراجعة (partially_approved).
        if ($pending > 0) {
            $status = 'partially_approved';
        } elseif ($approved === $total) {
            $status = 'approved';
        } elseif ($approved === 0) {
            $status = 'rejected';               // رفض العميل كل المرشّحين → يلزم إصدار جديد
        } else {
            $status = 'partially_approved';
        }
        $v->update(['status' => $status, 'decided_at' => now()]);
        $v->shortlist->update(['status' => $status]);

        $this->announceDecision($item, $v, $decision, $reason, $status, $pending);
    }

    /**
     * قرار العميل كان يُكتب بصمت: لا أثر تدقيق ولا إشعار.
     *
     * البوابة تَعِد العميل صراحةً بأن «قرارك يصل فريق الوكالة فورًا»، والوكالة
     * هي التي تُنشئ التعاون بعده — فبلا إشارة تقف الرحلة عند قرار مكتوب في
     * القاعدة لا يعلم به أحد. وهو أيضًا قرار تجاري (من اعتُمد، متى، ولماذا)
     * يستحقّ أثرًا كبقيّة الانتقالات.
     */
    private function announceDecision(
        CampaignShortlistItem $item,
        CampaignShortlistVersion $v,
        string $decision,
        ?string $reason,
        string $versionStatus,
        int $pending,
    ): void {
        $campaign = $v->shortlist->campaign;
        if (! $campaign) {
            return;
        }

        $creatorName = $item->creator?->display_name ?? "#{$item->creator_id}";

        AuditLogger::log("shortlist.item_{$decision}", $item, [
            'campaign_id' => $campaign->id,
            'creator_id' => $item->creator_id,
            'version' => (int) $v->version,
            'version_status' => $versionStatus,
            'reason' => $reason,
        ], $campaign->tenant_id, null);

        if (! $campaign->created_by) {
            return;
        }

        $verb = $decision === 'approved' ? 'اعتمد' : 'رفض';
        // بقاء معلّقات يعني أن الدور ما زال على العميل — لا تُستدعى الوكالة بعد.
        $body = $pending > 0
            ? "ما زال {$pending} مرشّحًا بانتظار قرار العميل."
            : ($versionStatus === 'rejected'
                ? 'رُفض كل المرشّحين — يلزم إصدار ترشيح جديد.'
                : 'اكتملت قرارات هذا الإصدار — أنشئ التعاون مع المعتمَدين.');

        $this->notifications->notify(
            $campaign->tenant_id,
            (int) $campaign->created_by,
            "shortlist.item_{$decision}",
            'general',
            "العميل {$verb} {$creatorName}",
            $reason ? "{$body} السبب: {$reason}" : $body,
            "/app/campaigns/{$campaign->id}/shortlist",
            ['campaign_id' => $campaign->id, 'shortlist_item_id' => $item->id],
            $item,
        );
    }
}
