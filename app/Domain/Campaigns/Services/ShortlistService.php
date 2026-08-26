<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlist;
use App\Domain\Campaigns\Models\CampaignShortlistItem;
use App\Domain\Campaigns\Models\CampaignShortlistVersion;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Creators\Models\Creator;
use App\Domain\Nomination\Services\NominationMatchService;
use Illuminate\Database\QueryException;
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

    /**
     * درجة ملاءمة مبدع للحملة (0..100) + أسباب — عبر المُطابِق القانونيّ الموحّد
     * ({@see NominationMatchService}). لا منطق تسجيل مكرّر.
     */
    public function matchScore(Campaign $campaign, Creator $creator): array
    {
        $r = app(NominationMatchService::class)->score($campaign, $creator);

        return ['score' => $r['score'], 'reasons' => $r['reasons']];
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

    public function removeItem(CampaignShortlistItem $item): void
    {
        $item->delete();
    }

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
        $needsAlt = $all->where('client_decision', 'needs_alternative')->count();
        // بقاء معلّقات: الدور على العميل. طلب بديل: الدور على الوكالة (changes_requested).
        // ثم اعتماد كامل / رفض كامل / مزيج.
        if ($pending > 0) {
            $status = 'partially_approved';
        } elseif ($needsAlt > 0) {
            $status = 'changes_requested';      // طلب العميل بديلًا لمرشّح أو أكثر → على الوكالة الاستبدال
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
     * N6 — تحويل المعتمَدين للتنفيذ: يُحوّل كل بند اعتمده العميل (أساسيّ، غير احتياط)
     * في الإصدار الحالي إلى «تعاون» عبر الخدمة القانونية الوحيدة للتنفيذ
     * ({@see CollaborationWorkflowService::offer}) — لا نطاق تنفيذ جديد.
     *
     * قواعد الأهلية (صارمة): المعتمَد فقط؛ المرفوض وطالب البديل لا يُحوَّلان أبدًا؛
     * والاحتياط لا يُحوَّل. إن بقي أيّ بند «بانتظار قرار العميل» تُمنع العملية (الرحلة
     * لم تكتمل بعد). Idempotent: البند المرتبط بتعاون مسبق يُتخطّى، والفهرس الفريد
     * (tenant_id, shortlist_item_id) يمنع الازدواج حتى عند التزامن/النقر المزدوج.
     *
     * @return array{created:int, skipped:int, eligible:int}
     */
    public function convertApprovedToCollaborations(CampaignShortlist $sl, int $actorId): array
    {
        $campaign = $sl->campaign;
        $v = $sl->currentVersion();
        $items = $v->items()->get();

        if ($items->where('client_decision', 'pending')->isNotEmpty()) {
            throw new \RuntimeException('لا يزال بعض المرشّحين بانتظار قرار العميل — أكمِل القرارات قبل التحويل للتنفيذ.');
        }

        $eligible = $items->where('client_decision', 'approved')->where('is_backup', false);
        if ($eligible->isEmpty()) {
            return ['created' => 0, 'skipped' => 0, 'eligible' => 0];
        }

        // البنود المحوّلة مسبقًا (لها تعاون) تُستبعَد — إعادة التشغيل لا تُنشئ ازدواجًا.
        $already = Collaboration::whereIn('shortlist_item_id', $eligible->pluck('id'))
            ->pluck('shortlist_item_id')->all();
        $todo = $eligible->whereNotIn('id', $already);

        $wf = app(\App\Domain\Collaborations\Services\CollaborationWorkflowService::class);
        $created = 0;
        foreach ($todo as $item) {
            try {
                $wf->offer($sl->tenant_id, [
                    'creator_id' => $item->creator_id,
                    'campaign_id' => $campaign->id,
                    'client_id' => $campaign->client_id,
                    'shortlist_item_id' => $item->id,
                    'title' => $campaign->name,
                    'brief' => $campaign->brief,
                    'fee_minor' => (int) $item->proposed_fee_minor,
                    'currency' => $campaign->currency,
                ], $actorId);
                $created++;
            } catch (QueryException $e) {
                // سباق نادر: نقر مزدوج تجاوز الفحص المسبق فاصطدم بالفهرس الفريد — تخطٍّ آمن.
                if (! str_contains($e->getMessage(), 'shortlist_item')) {
                    throw $e;
                }
            }
        }

        AuditLogger::log('nomination.converted', $campaign, [
            'created' => $created, 'eligible' => $eligible->count(), 'version' => (int) $v->version,
        ], $sl->tenant_id, $actorId);

        return ['created' => $created, 'skipped' => $eligible->count() - $created, 'eligible' => $eligible->count()];
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

        $verb = match ($decision) {
            'approved' => 'اعتمد',
            'needs_alternative' => 'طلب بديلًا عن',
            default => 'رفض',
        };
        // بقاء معلّقات يعني أن الدور ما زال على العميل — لا تُستدعى الوكالة بعد.
        $body = $pending > 0
            ? "ما زال {$pending} مرشّحًا بانتظار قرار العميل."
            : match ($versionStatus) {
                'rejected' => 'رُفض كل المرشّحين — يلزم إصدار ترشيح جديد.',
                'changes_requested' => 'طلب العميل بديلًا عن مرشّح أو أكثر — اقترح بدائل مناسبة.',
                default => 'اكتملت قرارات هذا الإصدار — أنشئ التعاون مع المعتمَدين.',
            };
        $cta = $decision === 'needs_alternative' ? 'اقتراح بديل' : 'مراجعة الترشيحات';

        $this->notifications->notify(
            $campaign->tenant_id,
            (int) $campaign->created_by,
            "shortlist.item_{$decision}",
            'campaigns',
            "العميل {$verb} {$creatorName}",
            $reason ? "{$body} السبب: {$reason}" : $body,
            "/app/campaigns/{$campaign->id}/shortlist",
            [
                // كائنات أعمال بأسمائها + زرّ خاصّ بالحدث — لبريد بشريّ واضح (بلا مصطلح تقنيّ).
                'objects' => [
                    ['type' => 'campaign', 'name' => $campaign->name],
                    ['type' => 'creator', 'name' => $creatorName],
                ],
                'cta_label' => $cta,
                'campaign_id' => $campaign->id,
                'shortlist_item_id' => $item->id,
            ],
            $item,
        );
    }
}
