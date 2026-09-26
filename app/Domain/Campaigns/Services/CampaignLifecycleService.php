<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlist;
use App\Domain\Campaigns\Models\CampaignShortlistItem;
use App\Domain\Campaigns\Models\CampaignShortlistVersion;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payout;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * محرّك مراحل الحملة الـ13 — مشتقّ من الحالة الفعليّة للنطاقات، لا من حقل زخرفيّ.
 *
 * كل مرحلة تُحسب من دليل حقيقيّ (سجلّات ترشيح/عقود/فواتير/تعاونات/محتوى/مستحقات).
 * لا تُخزَّن «المرحلة الحالية» في عمود قابل للتلاعب: `campaign.stage = 7` ممنوع.
 * تُعيد استخدام دليل الإغلاق نفسه (`openObligations`) لمنع إغلاق حملة بالتزامات مفتوحة.
 *
 * تُفصَل الحالة التشغيلية عن المالية: قد تُغلَق الحملة تشغيليًّا بينما يبقى تحصيل
 * العميل أو صرف المبدع معلّقًا.
 */
class CampaignLifecycleService
{
    /** المفاتيح القانونية للمراحل الـ13 بالترتيب. */
    public const STAGES = [
        'creation', 'nomination', 'internal_approval', 'send_to_client', 'client_decision',
        'quotation_contract', 'client_collection', 'creator_booking', 'scheduling',
        'creator_finance', 'publishing', 'archive_performance', 'closure',
    ];

    private const LABELS = [
        'creation' => ['إنشاء الحملة', 'Campaign Creation', 'مدير الحملة'],
        'nomination' => ['الترشيح', 'Creator Nomination', 'مدير الحملة'],
        'internal_approval' => ['الاعتماد الداخلي', 'Internal Approval', 'العمليات'],
        'send_to_client' => ['إرسال للعميل', 'Send to Client', 'مدير الحملة'],
        'client_decision' => ['قرار العميل', 'Client Decision', 'العميل'],
        'quotation_contract' => ['عرض السعر والعقد', 'Quotation & Contract', 'المالية'],
        'client_collection' => ['تحصيل العميل', 'Client Collection', 'المالية'],
        'creator_booking' => ['حجز المؤثرين', 'Creator Booking', 'المبدع'],
        'scheduling' => ['الجدولة', 'Scheduling', 'مدير الحملة'],
        'creator_finance' => ['الحوالات المالية', 'Creator Finance', 'المالية'],
        'publishing' => ['النشر وإثباته', 'Publishing & Proof', 'المبدع'],
        'archive_performance' => ['المحتوى والأداء', 'Archive & Performance', 'مدير الحملة'],
        'closure' => ['إقفال الحملة', 'Campaign Closure', 'مدير الحملة'],
    ];

    /**
     * @return array{
     *   stages: array<int,array<string,mixed>>, current: ?string, current_label: ?string,
     *   progress: int, completed: int, total: int, operational: array, financial: array
     * }
     */
    public function forCampaign(Campaign $c): array
    {
        $s = $this->gather($c);
        $link = fn (string $path) => "/app{$path}";

        // خرائط المفاتيح لأصحاب المراحل — الترجمة لغة-واعية عبر trans (العربية بايت-مطابقة للأصل).
        $ownerKeys = ['مدير الحملة' => 'manager', 'العمليات' => 'ops', 'العميل' => 'client', 'المالية' => 'finance', 'المبدع' => 'creator'];
        $stages = [];
        foreach (self::STAGES as $key) {
            [$labelAr, $labelEn, $owner] = self::LABELS[$key];
            $d = $this->derive($key, $c, $s, $link);
            $stages[] = array_merge([
                'key' => $key,
                'label' => trans("campaigns.lc_stage_{$key}"),
                'label_en' => $labelEn,
                'owner' => isset($ownerKeys[$owner]) ? trans("campaigns.lc_owner_{$ownerKeys[$owner]}") : $owner,
            ], $d);
        }

        // المرحلة الحالية = أوّل مرحلة غير مكتملة (أو الإقفال إن اكتمل الكل)
        $completed = 0;
        $current = null;
        foreach ($stages as $st) {
            if ($st['state'] === 'complete') {
                $completed++;
            } elseif ($current === null) {
                $current = $st['key'];
            }
        }
        $currentLabel = trans('campaigns.lc_stage_'.($current ?? 'closure'));

        // فصل الحالة التشغيلية عن المالية (درس تشغيليّ: قد تُغلَق تشغيليًّا ويبقى المال معلّقًا)
        $byKey = collect($stages)->keyBy('key');
        $opDone = collect(['nomination', 'client_decision', 'creator_booking', 'scheduling', 'publishing'])
            ->every(fn ($k) => $byKey[$k]['state'] === 'complete');
        $collectionDone = $byKey['client_collection']['state'] === 'complete';
        $payoutDone = $byKey['creator_finance']['state'] === 'complete';

        return [
            'stages' => $stages,
            'current' => $current,
            'current_label' => $currentLabel,
            'completed' => $completed,
            'total' => count(self::STAGES),
            'progress' => (int) round($completed / count(self::STAGES) * 100),
            'operational' => [
                'state' => $c->status === 'completed' ? 'closed' : ($opDone ? 'ready_to_close' : 'in_progress'),
                'label' => $c->status === 'completed' ? trans('campaigns.lc_op_closed') : ($opDone ? trans('campaigns.lc_op_ready') : trans('campaigns.lc_op_in_progress')),
            ],
            'financial' => [
                'collection' => $collectionDone ? 'settled' : ($s['invoiceCount'] ? 'outstanding' : 'not_started'),
                'payout' => $payoutDone ? 'settled' : ($s['payoutCount'] ? 'outstanding' : 'not_started'),
                'settled' => $collectionDone && $payoutDone,
                'label' => ($collectionDone && $payoutDone) ? trans('campaigns.lc_fin_settled')
                    : ((! $collectionDone && $s['invoiceCount']) ? trans('campaigns.lc_fin_collection_pending')
                        : ((! $payoutDone && $s['payoutCount']) ? trans('campaigns.lc_fin_payout_pending') : trans('campaigns.lc_fin_none'))),
            ],
        ];
    }

    /** يجمع الحالة الحقيقية مرّة واحدة داخل نطاق مستأجر الحملة (يستعيد السياق حتى عند استثناء). */
    private function gather(Campaign $c): array
    {
        return TenantContext::withTenant($c->tenant_id, function () use ($c) {
            $delivs = $c->deliverables()->get(['id', 'creator_id', 'due_date', 'fee_minor', 'quantity']);

            $shortlist = CampaignShortlist::where('campaign_id', $c->id)->first();
            $versions = collect();
            $items = collect();
            if ($shortlist) {
                $versions = CampaignShortlistVersion::where('shortlist_id', $shortlist->id)->get(['id', 'status', 'submitted_at', 'decided_at']);
                $latest = $versions->sortByDesc('id')->first();
                if ($latest) {
                    $items = CampaignShortlistItem::where('shortlist_version_id', $latest->id)->get(['id', 'client_decision']);
                }
            }

            $collabCounts = Collaboration::where('campaign_id', $c->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
            $contractCounts = Contract::where('campaign_id', $c->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
            $invoiceCount = Invoice::where('campaign_id', $c->id)->count();
            $openInvoices = Invoice::where('campaign_id', $c->id)->whereIn('status', Invoice::OPEN)->count();
            $payoutCount = Payout::where('campaign_id', $c->id)->count();
            $openPayouts = Payout::where('campaign_id', $c->id)->whereIn('status', Payout::OPEN)->count();

            $content = ContentItem::where('campaign_id', $c->id)->get(['id', 'status', 'published_url', 'scheduled_at', 'results_at']);

            return [
                'delivs' => $delivs,
                'submittedVersion' => $versions->firstWhere(fn ($v) => $v->submitted_at !== null),
                'internallyApproved' => $versions->contains(fn ($v) => $v->status !== 'draft'),
                'latestVersionStatus' => $versions->sortByDesc('id')->first()?->status,
                'items' => $items,
                'decidedItems' => $items->whereIn('client_decision', ['approved', 'rejected'])->count(),
                'approvedItems' => $items->where('client_decision', 'approved')->count(),
                'rejectedItems' => $items->where('client_decision', 'rejected')->count(),
                'collabCounts' => $collabCounts,
                'contractCounts' => $contractCounts,
                'invoiceCount' => $invoiceCount,
                'openInvoices' => $openInvoices,
                'payoutCount' => $payoutCount,
                'openPayouts' => $openPayouts,
                'content' => $content,
            ];
        });
    }

    /** يشتقّ حالة مرحلة واحدة من الدليل المُجمَّع. */
    private function derive(string $key, Campaign $c, array $s, callable $link): array
    {
        $none = ['state' => 'not_started', 'evidence' => null, 'blockers' => [], 'missing' => [], 'next_action' => null, 'due_date' => null, 'entities' => []];
        $make = fn (string $state, ?string $ev, array $blockers = [], array $missing = [], ?array $next = null, array $entities = []) => compact('state', 'blockers', 'missing', 'entities') + ['evidence' => $ev, 'next_action' => $next, 'due_date' => null];

        $collab = $s['collabCounts'];
        $bookedCollabs = (int) ($collab['accepted'] ?? 0) + (int) ($collab['in_progress'] ?? 0) + (int) ($collab['submitted'] ?? 0) + (int) ($collab['approved'] ?? 0) + (int) ($collab['completed'] ?? 0);
        $offeredCollabs = (int) ($collab['offered'] ?? 0);
        $declinedCollabs = (int) ($collab['declined'] ?? 0);
        $signedContracts = (int) ($s['contractCounts']['signed'] ?? 0) + (int) ($s['contractCounts']['active'] ?? 0) + (int) ($s['contractCounts']['completed'] ?? 0);
        $sentContracts = (int) ($s['contractCounts']['sent'] ?? 0);
        $content = $s['content'];
        $publishedProof = $content->filter(fn ($x) => (bool) $x->published_url)->count();
        $withMetrics = $content->filter(fn ($x) => $x->results_at !== null)->count();
        $datedDelivs = $s['delivs']->filter(fn ($d) => $d->due_date !== null)->count();
        $delivCount = $s['delivs']->count();

        return match ($key) {
            'creation' => (($c->budget_minor > 0 && $delivCount > 0)
                ? $make('complete', trans('campaigns.lc_creation_ev', ['num' => $c->campaign_number, 'n' => $delivCount]), entities: ['deliverables' => $delivCount])
                : $make('in_progress', trans('campaigns.lc_creation_ip'),
                    missing: array_values(array_filter([$c->budget_minor > 0 ? null : trans('campaigns.lc_missing_budget'), $delivCount > 0 ? null : trans('campaigns.lc_missing_deliv')])),
                    next: ['title' => trans('campaigns.lc_creation_next'), 'link' => $link("/campaigns/{$c->id}")])),

            'nomination' => ($s['items']->count() > 0
                ? $make('complete', trans('campaigns.lc_nomination_ev', ['n' => $s['items']->count()]), entities: ['nominated' => $s['items']->count()])
                : $make('not_started', null, missing: [trans('campaigns.lc_nomination_missing')],
                    next: ['title' => trans('campaigns.lc_nomination_next'), 'link' => $link("/campaigns/{$c->id}/shortlist")])),

            'internal_approval' => ($s['internallyApproved']
                ? $make('complete', trans('campaigns.lc_internal_ev'), entities: [])
                : ($s['items']->count() > 0
                    ? $make('in_progress', trans('campaigns.lc_internal_ip'), missing: [trans('campaigns.lc_internal_missing')],
                        next: ['title' => trans('campaigns.lc_internal_next'), 'link' => $link("/campaigns/{$c->id}/shortlist")])
                    : $none)),

            'send_to_client' => ($s['submittedVersion']
                ? $make('complete', trans('campaigns.lc_send_ev'), entities: [])
                : ($s['internallyApproved']
                    ? $make('in_progress', trans('campaigns.lc_send_ip'), missing: [trans('campaigns.lc_send_missing')],
                        next: ['title' => trans('campaigns.lc_send_next'), 'link' => $link("/campaigns/{$c->id}/shortlist")])
                    : $none)),

            'client_decision' => (function () use ($s, $make, $none, $link, $c) {
                if (! $s['submittedVersion']) {
                    return $none;
                }
                if ($s['rejectedItems'] > 0 && $s['approvedItems'] === 0) {
                    return $make('blocked', null, blockers: [trans('campaigns.lc_decision_blocker')],
                        next: ['title' => trans('campaigns.lc_decision_next_alt'), 'link' => $link("/campaigns/{$c->id}/shortlist")]);
                }
                if (in_array($s['latestVersionStatus'], ['approved', 'partially_approved'], true) || ($s['items']->count() > 0 && $s['decidedItems'] >= $s['items']->count())) {
                    return $make('complete', trans('campaigns.lc_decision_ev', ['n' => $s['approvedItems']]), entities: ['approved' => $s['approvedItems'], 'rejected' => $s['rejectedItems']]);
                }

                return $make('in_progress', trans('campaigns.lc_decision_ip'), missing: [trans('campaigns.lc_decision_missing')]);
            })(),

            'quotation_contract' => ($signedContracts > 0
                ? $make('complete', trans('campaigns.lc_quote_ev'), entities: ['signed' => $signedContracts])
                : ($sentContracts > 0
                    ? $make('in_progress', trans('campaigns.lc_quote_ip'), missing: [trans('campaigns.lc_quote_missing_sign')],
                        next: ['title' => trans('campaigns.lc_quote_next_follow'), 'link' => $link('/contracts')])
                    : $make('not_started', null, missing: [trans('campaigns.lc_quote_missing_issue')],
                        next: ['title' => trans('campaigns.lc_quote_next_issue'), 'link' => $link('/contracts')]))),

            'client_collection' => ($s['invoiceCount'] > 0 && $s['openInvoices'] === 0
                ? $make('complete', trans('campaigns.lc_collection_ev'), entities: ['invoices' => $s['invoiceCount']])
                : ($s['openInvoices'] > 0
                    ? $make('in_progress', trans('campaigns.lc_collection_ip', ['n' => $s['openInvoices']]), missing: [trans('campaigns.lc_collection_missing')],
                        next: ['title' => trans('campaigns.lc_collection_next_follow'), 'link' => $link('/invoices')])
                    : $make('not_started', null, missing: [trans('campaigns.lc_collection_missing_issue')],
                        next: ['title' => trans('campaigns.lc_collection_next_issue'), 'link' => $link('/invoices')]))),

            'creator_booking' => (function () use ($bookedCollabs, $offeredCollabs, $declinedCollabs, $make, $none, $link) {
                if ($bookedCollabs === 0 && $offeredCollabs === 0 && $declinedCollabs > 0) {
                    return $make('blocked', null, blockers: [trans('campaigns.lc_booking_blocker')],
                        next: ['title' => trans('campaigns.lc_booking_next_alt'), 'link' => $link('/collaborations')]);
                }
                if ($bookedCollabs > 0 && $offeredCollabs === 0) {
                    return $make('complete', trans('campaigns.lc_booking_ev', ['n' => $bookedCollabs]), entities: ['booked' => $bookedCollabs]);
                }
                if ($offeredCollabs > 0) {
                    return $make('in_progress', trans('campaigns.lc_booking_ip', ['n' => $offeredCollabs]), missing: [trans('campaigns.lc_booking_missing')],
                        next: ['title' => trans('campaigns.lc_booking_next_follow'), 'link' => $link('/collaborations')]);
                }

                return $none;
            })(),

            'scheduling' => ($delivCount > 0 && $datedDelivs === $delivCount
                ? $make('complete', trans('campaigns.lc_sched_ev'), entities: ['scheduled' => $datedDelivs])
                : ($datedDelivs > 0
                    ? $make('in_progress', trans('campaigns.lc_sched_ip', ['done' => $datedDelivs, 'total' => $delivCount]), missing: [trans('campaigns.lc_sched_missing_rest')],
                        next: ['title' => trans('campaigns.lc_sched_next'), 'link' => $link("/campaigns/{$c->id}")])
                    : $make('not_started', null, missing: [trans('campaigns.lc_sched_missing_all')]))),

            'creator_finance' => ($s['payoutCount'] > 0 && $s['openPayouts'] === 0
                ? $make('complete', trans('campaigns.lc_finance_ev'), entities: ['payouts' => $s['payoutCount']])
                : ($s['openPayouts'] > 0
                    ? $make('in_progress', trans('campaigns.lc_finance_ip', ['n' => $s['openPayouts']]), missing: [trans('campaigns.lc_finance_missing')],
                        next: ['title' => trans('campaigns.lc_finance_next'), 'link' => $link('/payouts')])
                    : $make('not_started', null, missing: [trans('campaigns.lc_finance_missing_create')]))),

            'publishing' => ($content->count() > 0 && $publishedProof === $content->count()
                ? $make('complete', trans('campaigns.lc_publish_ev', ['n' => $publishedProof]), entities: ['published' => $publishedProof])
                : ($publishedProof > 0
                    ? $make('in_progress', trans('campaigns.lc_publish_ip', ['done' => $publishedProof, 'total' => $content->count()]), missing: [trans('campaigns.lc_publish_missing_rest')],
                        next: ['title' => trans('campaigns.lc_publish_next'), 'link' => $link('/content')])
                    : $make('not_started', null, missing: [trans('campaigns.lc_publish_missing_all')]))),

            'archive_performance' => ($publishedProof > 0 && $withMetrics === $publishedProof
                ? $make('complete', trans('campaigns.lc_archive_ev', ['n' => $withMetrics]), entities: ['with_metrics' => $withMetrics])
                : ($publishedProof > 0
                    ? $make('in_progress', trans('campaigns.lc_archive_ip', ['done' => $withMetrics, 'total' => $publishedProof]), missing: [trans('campaigns.lc_archive_missing')],
                        next: ['title' => trans('campaigns.lc_archive_next'), 'link' => $link('/content')])
                    : $none)),

            'closure' => (function () use ($c, $s, $make) {
                $obligations = array_values(array_filter([
                    (int) (($s['collabCounts']['offered'] ?? 0) + ($s['collabCounts']['accepted'] ?? 0) + ($s['collabCounts']['in_progress'] ?? 0) + ($s['collabCounts']['submitted'] ?? 0)) ? trans('campaigns.lc_obl_collabs') : null,
                    $s['content']->whereIn('status', ['submitted', 'agency_review', 'client_review', 'changes_requested'])->count() ? trans('campaigns.lc_obl_content') : null,
                    $s['openInvoices'] ? trans('campaigns.lc_collection_ip', ['n' => $s['openInvoices']]) : null,
                    $s['openPayouts'] ? trans('campaigns.lc_finance_ip', ['n' => $s['openPayouts']]) : null,
                ]));
                if ($c->status === 'completed') {
                    return $make('complete', trans('campaigns.lc_closure_ev'), entities: []);
                }
                if ($obligations) {
                    return $make('blocked', null, blockers: $obligations, missing: [trans('campaigns.lc_closure_missing_blocked')]);
                }

                return $make('in_progress', trans('campaigns.lc_closure_ip'), missing: [trans('campaigns.lc_closure_missing')],
                    next: ['title' => trans('campaigns.lc_closure_next'), 'link' => "/app/campaigns/{$c->id}"]);
            })(),

            default => $none,
        };
    }
}
