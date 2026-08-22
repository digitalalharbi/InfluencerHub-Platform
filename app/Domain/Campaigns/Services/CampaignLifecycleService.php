<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlist;
use App\Domain\Campaigns\Models\CampaignShortlistItem;
use App\Domain\Campaigns\Models\CampaignShortlistVersion;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Content\Models\ContentItem;
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

        $stages = [];
        foreach (self::STAGES as $key) {
            [$labelAr, $labelEn, $owner] = self::LABELS[$key];
            $d = $this->derive($key, $c, $s, $link);
            $stages[] = array_merge([
                'key' => $key,
                'label' => $labelAr,
                'label_en' => $labelEn,
                'owner' => $owner,
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
        $currentLabel = $current ? self::LABELS[$current][0] : self::LABELS['closure'][0];

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
                'label' => $c->status === 'completed' ? 'مُغلَقة تشغيليًّا' : ($opDone ? 'جاهزة للإغلاق' : 'قيد التنفيذ'),
            ],
            'financial' => [
                'collection' => $collectionDone ? 'settled' : ($s['invoiceCount'] ? 'outstanding' : 'not_started'),
                'payout' => $payoutDone ? 'settled' : ($s['payoutCount'] ? 'outstanding' : 'not_started'),
                'settled' => $collectionDone && $payoutDone,
                'label' => ($collectionDone && $payoutDone) ? 'مُسوّاة ماليًّا'
                    : ((! $collectionDone && $s['invoiceCount']) ? 'تحصيل العميل معلّق'
                        : ((! $payoutDone && $s['payoutCount']) ? 'صرف المبدع معلّق' : 'لا التزام ماليّ بعد')),
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
        $make = fn (string $state, ?string $ev, array $blockers = [], array $missing = [], ?array $next = null, array $entities = []) =>
            compact('state', 'blockers', 'missing', 'entities') + ['evidence' => $ev, 'next_action' => $next, 'due_date' => null];

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
                ? $make('complete', "أُنشئت الحملة #{$c->campaign_number} (ميزانية محدّدة، {$delivCount} مخرجًا)", entities: ['deliverables' => $delivCount])
                : $make('in_progress', 'الحملة قيد الإعداد',
                    missing: array_values(array_filter([$c->budget_minor > 0 ? null : 'حدّد الميزانية', $delivCount > 0 ? null : 'أضِف مخرجًا واحدًا على الأقل'])),
                    next: ['title' => 'استكمال بيانات الحملة', 'link' => $link("/campaigns/{$c->id}")])),

            'nomination' => ($s['items']->count() > 0
                ? $make('complete', "رُشِّح {$s['items']->count()} مؤثرًا", entities: ['nominated' => $s['items']->count()])
                : $make('not_started', null, missing: ['رشّح مؤثرين للحملة'],
                    next: ['title' => 'بدء الترشيح', 'link' => $link("/campaigns/{$c->id}/shortlist")])),

            'internal_approval' => ($s['internallyApproved']
                ? $make('complete', 'اعتمد الفريق نسخة الترشيح داخليًّا وقفلها', entities: [])
                : ($s['items']->count() > 0
                    ? $make('in_progress', 'الترشيح مسوّدة — بانتظار اعتماد الفريق', missing: ['اعتمد النسخة داخليًّا'],
                        next: ['title' => 'اعتماد الترشيح داخليًّا', 'link' => $link("/campaigns/{$c->id}/shortlist")])
                    : $none)),

            'send_to_client' => ($s['submittedVersion']
                ? $make('complete', 'أُرسلت نسخة الترشيح للعميل', entities: [])
                : ($s['internallyApproved']
                    ? $make('in_progress', 'معتمدة داخليًّا — لم تُرسل بعد', missing: ['أرسل النسخة للعميل'],
                        next: ['title' => 'إرسال الترشيح للعميل', 'link' => $link("/campaigns/{$c->id}/shortlist")])
                    : $none)),

            'client_decision' => (function () use ($s, $make, $none, $link, $c) {
                if (! $s['submittedVersion']) {
                    return $none;
                }
                if ($s['rejectedItems'] > 0 && $s['approvedItems'] === 0) {
                    return $make('blocked', null, blockers: ['رفض العميل المرشّحين — رشّح بدائل'],
                        next: ['title' => 'ترشيح بدائل', 'link' => $link("/campaigns/{$c->id}/shortlist")]);
                }
                if (in_array($s['latestVersionStatus'], ['approved', 'partially_approved'], true) || ($s['items']->count() > 0 && $s['decidedItems'] >= $s['items']->count())) {
                    return $make('complete', "قرّر العميل ({$s['approvedItems']} معتمد)", entities: ['approved' => $s['approvedItems'], 'rejected' => $s['rejectedItems']]);
                }

                return $make('in_progress', 'بانتظار قرار العميل', missing: ['ينتظر قرار العميل على المرشّحين']);
            })(),

            'quotation_contract' => ($signedContracts > 0
                ? $make('complete', 'العقد موقّع/فعّال', entities: ['signed' => $signedContracts])
                : ($sentContracts > 0
                    ? $make('in_progress', 'العقد مُرسَل — بانتظار التوقيع', missing: ['توقيع العقد'],
                        next: ['title' => 'متابعة توقيع العقد', 'link' => $link('/contracts')])
                    : $make('not_started', null, missing: ['أصدر عرض السعر/العقد'],
                        next: ['title' => 'إصدار العقد', 'link' => $link('/contracts')]))),

            'client_collection' => ($s['invoiceCount'] > 0 && $s['openInvoices'] === 0
                ? $make('complete', 'حُصِّلت كل الفواتير', entities: ['invoices' => $s['invoiceCount']])
                : ($s['openInvoices'] > 0
                    ? $make('in_progress', "{$s['openInvoices']} فاتورة لم تُحصَّل", missing: ['تحصيل الفواتير المفتوحة'],
                        next: ['title' => 'متابعة التحصيل', 'link' => $link('/invoices')])
                    : $make('not_started', null, missing: ['أصدر فاتورة العميل'],
                        next: ['title' => 'إصدار فاتورة', 'link' => $link('/invoices')]))),

            'creator_booking' => (function () use ($bookedCollabs, $offeredCollabs, $declinedCollabs, $make, $none, $link) {
                if ($bookedCollabs === 0 && $offeredCollabs === 0 && $declinedCollabs > 0) {
                    return $make('blocked', null, blockers: ['اعتذر المبدع — احجز بديلًا'],
                        next: ['title' => 'حجز بديل', 'link' => $link('/collaborations')]);
                }
                if ($bookedCollabs > 0 && $offeredCollabs === 0) {
                    return $make('complete', "حُجز {$bookedCollabs} مبدعًا (قبِلوا)", entities: ['booked' => $bookedCollabs]);
                }
                if ($offeredCollabs > 0) {
                    return $make('in_progress', "{$offeredCollabs} عرض بانتظار قبول المبدع", missing: ['بانتظار قبول المبدع'],
                        next: ['title' => 'متابعة الحجز', 'link' => $link('/collaborations')]);
                }

                return $none;
            })(),

            'scheduling' => ($delivCount > 0 && $datedDelivs === $delivCount
                ? $make('complete', 'كل المخرجات مجدولة بتواريخ', entities: ['scheduled' => $datedDelivs])
                : ($datedDelivs > 0
                    ? $make('in_progress', "{$datedDelivs}/{$delivCount} مخرج مجدول", missing: ['حدّد تواريخ النشر لبقيّة المخرجات'],
                        next: ['title' => 'جدولة المخرجات', 'link' => $link("/campaigns/{$c->id}")])
                    : $make('not_started', null, missing: ['حدّد تواريخ النشر للمخرجات']))),

            'creator_finance' => ($s['payoutCount'] > 0 && $s['openPayouts'] === 0
                ? $make('complete', 'صُرفت كل المستحقات', entities: ['payouts' => $s['payoutCount']])
                : ($s['openPayouts'] > 0
                    ? $make('in_progress', "{$s['openPayouts']} مستحقًّا لم يُصرف", missing: ['اعتماد/صرف المستحقات'],
                        next: ['title' => 'متابعة المستحقات', 'link' => $link('/payouts')])
                    : $make('not_started', null, missing: ['أنشئ مستحقات المبدعين']))),

            'publishing' => ($content->count() > 0 && $publishedProof === $content->count()
                ? $make('complete', "نُشر وأُثبت {$publishedProof} محتوى", entities: ['published' => $publishedProof])
                : ($publishedProof > 0
                    ? $make('in_progress', "{$publishedProof}/{$content->count()} نُشر بإثبات", missing: ['إثبات نشر بقيّة المحتوى'],
                        next: ['title' => 'التحقّق من النشر', 'link' => $link('/content')])
                    : $make('not_started', null, missing: ['نشر المحتوى وإرفاق رابط الإثبات']))),

            'archive_performance' => ($publishedProof > 0 && $withMetrics === $publishedProof
                ? $make('complete', "أُرشِف وسُجِّل أداء {$withMetrics} محتوى", entities: ['with_metrics' => $withMetrics])
                : ($publishedProof > 0
                    ? $make('in_progress', "أداء {$withMetrics}/{$publishedProof} مُسجّل", missing: ['سجّل مقاييس الأداء (يدويًّا أو عبر تكامل)'],
                        next: ['title' => 'تسجيل الأداء', 'link' => $link('/content')])
                    : $none)),

            'closure' => (function () use ($c, $s, $bookedCollabs, $make) {
                $obligations = array_values(array_filter([
                    (int) (($s['collabCounts']['offered'] ?? 0) + ($s['collabCounts']['accepted'] ?? 0) + ($s['collabCounts']['in_progress'] ?? 0) + ($s['collabCounts']['submitted'] ?? 0)) ? 'تعاونات لم تُغلَق' : null,
                    $s['content']->whereIn('status', ['submitted', 'agency_review', 'client_review', 'changes_requested'])->count() ? 'محتوى في المراجعة' : null,
                    $s['openInvoices'] ? "{$s['openInvoices']} فاتورة لم تُحصَّل" : null,
                    $s['openPayouts'] ? "{$s['openPayouts']} مستحقًّا لم يُصرف" : null,
                ]));
                if ($c->status === 'completed') {
                    return $make('complete', 'أُغلقت الحملة', entities: []);
                }
                if ($obligations) {
                    return $make('blocked', null, blockers: $obligations, missing: ['أغلِق الالتزامات المفتوحة قبل الإقفال']);
                }

                return $make('in_progress', 'الالتزامات مكتملة — جاهزة للإقفال', missing: ['أغلِق الحملة وأصدر التقرير'],
                    next: ['title' => 'إقفال الحملة', 'link' => "/app/campaigns/{$c->id}"]);
            })(),

            default => $none,
        };
    }
}
