<?php

namespace App\Support\Analytics;

use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات الحملات — شرائح تشغيلية ومؤشرات تقدّم مشتقّة من بيانات حقيقية.
 * "متأخرة" = نشطة/متوقفة تجاوزت تاريخ الانتهاء. "بانتظار العميل" = لديها محتوى في client_review.
 * التقدّم = (مخرجات معتمدة/منشورة) ÷ (إجمالي المخرجات).
 */
class CampaignAnalytics
{
    private const DONE_DELIV = ['approved', 'published'];

    /**
     * ملخّص الحملات — عدّادات الحالات في استعلام تجميعي واحد.
     * كانت ستة استعلامات متطابقة عدا قيمة الحالة (نمط N+1 في اللوحة).
     * «متأخرة» و«بانتظار العميل» شرطان مركّبان فيبقيان استعلامَين مستقلَّين.
     */
    public static function summary(): array
    {
        $byStatus = Campaign::query()->groupBy('status')->selectRaw('status, count(*) as c')->pluck('c', 'status');
        $c = fn (string $st) => (int) ($byStatus[$st] ?? 0);

        return [
            'total' => (int) $byStatus->sum(),
            'active' => $c('active'),
            'planning' => $c('planning'),
            'paused' => $c('paused'),
            'completed' => $c('completed'),
            'draft' => $c('draft'),
            'late' => Campaign::query()->whereIn('status', ['active', 'paused'])->whereNotNull('end_date')
                ->whereDate('end_date', '<', now())->count(),
            'awaiting_client' => Campaign::query()->whereExists(fn ($q) => $q->select(DB::raw(1))->from('content_items')
                ->whereColumn('content_items.campaign_id', 'campaigns.id')->where('content_items.status', 'client_review'))->count(),
        ];
    }

    public static function applySegment($query, ?string $seg)
    {
        return match ($seg) {
            'active' => $query->where('status', 'active'),
            'planning' => $query->where('status', 'planning'),
            'paused' => $query->where('status', 'paused'),
            'completed' => $query->where('status', 'completed'),
            'draft' => $query->where('status', 'draft'),
            'late' => $query->whereIn('status', ['active', 'paused'])->whereNotNull('end_date')->whereDate('end_date', '<', now()),
            'awaiting_client' => $query->whereExists(fn ($q) => $q->select(DB::raw(1))->from('content_items')
                ->whereColumn('content_items.campaign_id', 'campaigns.id')->where('content_items.status', 'client_review')),
            default => $query,
        };
    }

    /** @param Collection<int,Campaign> $campaigns */
    public static function forPage(Collection $campaigns): array
    {
        $ids = $campaigns->pluck('id')->all();
        if (! $ids) return [];
        $totalDeliv = self::countBy(CampaignDeliverable::query(), $ids, 'campaign_id');
        $doneDeliv = self::countBy(CampaignDeliverable::query()->whereIn('status', self::DONE_DELIV), $ids, 'campaign_id');
        $creators = Collaboration::query()->whereIn('campaign_id', $ids)
            ->selectRaw('campaign_id as k, count(distinct creator_id) as v')->groupBy('campaign_id')->pluck('v', 'k')->all();
        $awaitingClient = self::countBy(ContentItem::query()->where('status', 'client_review'), $ids, 'campaign_id');
        $platforms = CampaignDeliverable::query()->whereIn('campaign_id', $ids)
            ->select('campaign_id', 'platform')->distinct()->get()->groupBy('campaign_id')
            ->map(fn ($g) => $g->pluck('platform')->unique()->values()->all())->all();

        $out = [];
        foreach ($campaigns as $c) {
            $total = (int) ($totalDeliv[$c->id] ?? 0);
            $done = (int) ($doneDeliv[$c->id] ?? 0);
            $late = in_array($c->status, ['active', 'paused'], true) && $c->end_date && $c->end_date->isPast();
            $out[$c->id] = [
                'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
                'deliverables' => $total,
                'creators' => (int) ($creators[$c->id] ?? 0),
                'platforms' => $platforms[$c->id] ?? [],
                'awaiting_client' => (int) ($awaitingClient[$c->id] ?? 0),
                'is_late' => $late,
            ];
        }
        return $out;
    }

    private static function countBy($query, array $ids, string $key): array
    {
        return $query->whereIn($key, $ids)->groupBy($key)->selectRaw("$key as k, count(*) as v")->pluck('v', 'k')->all();
    }

    /** قائمة جاهزية ذكية — تُحسب آليًا من بيانات حقيقية (لا تحديد يدوي). */
    /**
     * قائمة الجاهزية التنفيذية — كل معيار حالةٌ صادقة (جاهز/يحتاج انتباه/محظور/لا ينطبق)
     * مع السبب والدليل والإجراء العامل. لا شطب للمكتمل: المكتمل «جاهز» لا «ملغى».
     */
    public static function readiness(\App\Domain\Campaigns\Models\Campaign $c, array $m): array
    {
        $delivs = $c->deliverables;
        $committed = (int) $delivs->sum(fn ($d) => (int) ($d->fee_minor ?? 0) * (int) $d->quantity);
        $content = $c->contentItems;
        $approved = $content->whereIn('status', ['approved', 'published'])->count();
        $budget = (int) $c->budget_minor;
        $cur = $c->currency ?: 'SAR';
        // الريال بالعربية (ر.س) في واجهة المستأجر؛ رمز ISO للعملات الأخرى فقط.
        $curLabel = $cur === 'SAR' ? 'ر.س' : $cur;
        $fmt = fn (int $minor) => number_format($minor / 100, 0) . ' ' . $curLabel;
        $unassigned = $delivs->whereNull('creator_id')->count();
        $cid = $c->id;

        // كل معيار: [label, state, reason, evidence, action]. الحالات:
        // ready | attention | blocked | not_applicable
        $mk = fn (string $label, string $state, string $reason, ?string $evidence, ?array $action) =>
            compact('label', 'state', 'reason', 'evidence', 'action');

        $clientOk = $c->client && in_array($c->client->status, ['active', 'qualified'], true);
        $brandNa = ! $c->brand_id;
        $brandOk = $brandNa || ($c->brand && $c->brand->status === 'approved');
        $overBudget = $budget > 0 && $committed > $budget;

        $items = [
            $mk('العميل نشِط', $clientOk ? 'ready' : 'blocked',
                $clientOk ? 'العميل مؤهّل للتعاقد.' : 'حالة العميل ليست نشِطة/مؤهّلة — لا يمكن المضي في التنفيذ.',
                $c->client ? 'الحالة الحالية: ' . __('statuses.' . $c->client->status) : 'لا عميل مرتبط',
                $c->client_id ? ['title' => 'فتح ملفّ العميل', 'link' => "/app/clients/{$c->client_id}"] : null),

            $mk('العلامة معتمدة', $brandNa ? 'not_applicable' : ($brandOk ? 'ready' : 'blocked'),
                $brandNa ? 'لا علامة مرتبطة بهذه الحملة.' : ($brandOk ? 'العلامة معتمدة.' : 'العلامة بانتظار اعتماد المراجعة.'),
                $c->brand ? 'العلامة: ' . $c->brand->name : null,
                $brandOk ? null : ['title' => 'مراجعة العلامات', 'link' => '/app/brand-reviews']),

            $mk('الميزانية محدّدة', $budget > 0 ? 'ready' : 'attention',
                $budget > 0 ? 'ميزانية الحملة محدّدة.' : 'لم تُحدَّد ميزانية بعد — يتعذّر ضبط الالتزامات.',
                $budget > 0 ? 'الميزانية: ' . $fmt($budget) : null,
                $budget > 0 ? null : ['title' => 'تحديد الميزانية', 'link' => "/app/campaigns/{$cid}"]),

            $mk('مخرجات مُضافة', $delivs->count() > 0 ? 'ready' : 'attention',
                $delivs->count() > 0 ? 'المخرجات مُضافة.' : 'أضِف مخرجًا واحدًا على الأقل لبدء التنفيذ.',
                $delivs->count() . ' مخرج',
                $delivs->count() > 0 ? null : ['title' => 'إضافة مخرج', 'link' => "/app/campaigns/{$cid}"]),

            $mk('كل مخرج مُسنَد لمبدع', $delivs->count() === 0 ? 'attention' : ($unassigned === 0 ? 'ready' : 'attention'),
                $delivs->count() === 0 ? 'لا مخرجات لإسنادها بعد.' : ($unassigned === 0 ? 'كل المخرجات مُسنَدة.' : "{$unassigned} مخرج بلا مبدع مُسنَد."),
                $delivs->count() ? ($delivs->count() - $unassigned) . '/' . $delivs->count() . ' مُسنَد' : null,
                $unassigned > 0 ? ['title' => 'إسناد المبدعين', 'link' => "/app/campaigns/{$cid}"] : null),

            $mk('ضمن الميزانية', $budget === 0 ? 'not_applicable' : ($overBudget ? 'blocked' : 'ready'),
                $budget === 0 ? 'الميزانية غير محدّدة بعد.' : ($overBudget ? 'الالتزامات تتجاوز الميزانية المعتمدة.' : 'الالتزامات ضمن الميزانية.'),
                $budget === 0 ? null : 'الميزانية ' . $fmt($budget) . ' · الالتزامات ' . $fmt($committed),
                $overBudget ? ['title' => 'مراجعة التكاليف', 'link' => "/app/campaigns/{$cid}#deliverables"] : null),

            $mk('المحتوى معتمد', $content->count() === 0 ? 'not_applicable' : ($approved === $content->count() ? 'ready' : 'attention'),
                $content->count() === 0 ? 'لا محتوى مُقدَّم بعد.' : ($approved === $content->count() ? 'كل المحتوى معتمد.' : ($content->count() - $approved) . ' عنصر بانتظار الاعتماد.'),
                $content->count() ? "{$approved}/{$content->count()} معتمد" : null,
                ($content->count() && $approved < $content->count()) ? ['title' => 'مراجعة المحتوى', 'link' => '/app/content'] : null),
        ];

        $ready = collect($items)->where('state', 'ready')->count();
        $blocked = collect($items)->where('state', 'blocked')->count();
        $applicable = collect($items)->where('state', '!=', 'not_applicable')->count();

        return [
            'items' => $items,
            'ready' => $ready, 'blocked' => $blocked,
            'done' => $ready, 'total' => $applicable,
            'percent' => $applicable ? (int) round($ready / $applicable * 100) : 100,
            'budget' => [
                'budgetMinor' => $budget,
                'committedMinor' => $committed,
                'remainingMinor' => $budget - $committed, // سالب = تجاوز
                'overBudget' => $overBudget,
                'variancePct' => $budget > 0 ? (int) round(($committed - $budget) / $budget * 100) : 0,
                'currency' => $cur,
            ],
        ];
    }

    /** مخطط زمني موحّد لأحداث الحملة (مراحل + تعاونات + محتوى) مرتّب زمنيًا تنازليًا. */
    public static function timeline(\App\Domain\Campaigns\Models\Campaign $c): array
    {
        $ev = [];
        foreach ($c->statusHistory as $h) {
            $ev[] = ['at' => $h->occurred_at ?? $h->created_at, 'icon' => 'rocket', 'tone' => 'primary',
                'text' => 'الحملة → ' . __('statuses.' . $h->to_status), 'meta' => ''];
        }
        foreach ($c->collaborations as $col) {
            $ev[] = ['at' => $col->created_at, 'icon' => 'git-merge', 'tone' => 'info',
                'text' => 'تعاون ' . __('statuses.' . $col->status), 'meta' => $col->creator?->display_name ?? ''];
        }
        foreach ($c->contentItems as $ci) {
            $ev[] = ['at' => $ci->created_at, 'icon' => 'image', 'tone' => 'accent',
                'text' => 'محتوى ' . __('statuses.' . $ci->status), 'meta' => $ci->creator?->display_name ?? $ci->title];
        }
        usort($ev, fn ($a, $b) => ($b['at']?->timestamp ?? 0) <=> ($a['at']?->timestamp ?? 0));
        return array_slice($ev, 0, 40);
    }

    /**
     * مركز قيادة الحملة: رحلة أصلية (مراحل مشتقّة من الحالة الفعلية) + الخطوة التالية.
     * مسمّيات ومنطق InfluencerHub الأصلية — لا نسخ لشريط مراحل مرجعي.
     */
    public static function commandCenter(\App\Domain\Campaigns\Models\Campaign $c, array $m): array
    {
        // رحلة أصلية مختصرة (7 مراحل) مربوطة بإشارات حقيقية
        $status = $c->status;
        $hasCreators = ($m['creators'] ?? 0) > 0;
        $hasContent = ($m['deliverables'] ?? 0) > 0;
        $awaitingClient = ($m['awaiting_client'] ?? 0) > 0;
        $progress = $m['progress'] ?? 0;

        $order = ['setup', 'planning', 'sourcing', 'production', 'review', 'publishing', 'closure'];
        $labels = [
            'setup' => 'الإعداد', 'planning' => 'التخطيط', 'sourcing' => 'الترشيح',
            'production' => 'الإنتاج', 'review' => 'المراجعة', 'publishing' => 'النشر', 'closure' => 'الإغلاق',
        ];
        // المرحلة الحالية المشتقّة
        $current = match (true) {
            $status === 'draft' => 'setup',
            $status === 'planning' => 'planning',
            $status === 'completed' => 'closure',
            $status === 'paused' => 'production',
            $awaitingClient => 'review',
            $progress >= 100 => 'publishing',
            $hasContent => 'production',
            $hasCreators => 'sourcing',
            default => 'planning',
        };
        $curIdx = array_search($current, $order, true);
        $stages = [];
        foreach ($order as $i => $key) {
            $stages[] = [
                'key' => $key, 'label' => $labels[$key],
                'state' => $status === 'completed' ? 'done'
                    : ($i < $curIdx ? 'done' : ($i === $curIdx ? 'current' : 'pending')),
            ];
        }

        // الخطوة التالية (إجراء رئيسي واحد واضح)
        $next = match ($current) {
            'setup' => ['نقل الحملة للتخطيط', 'حدّد النطاق والميزانية ثم انقلها للتخطيط.', "/app/campaigns/{$c->id}"],
            'planning' => ['بدء الترشيح', 'رشّح المؤثرين المناسبين للحملة.', "/app/campaigns/{$c->id}/shortlist"],
            'sourcing' => ['إرسال الترشيحات للعميل', 'أرسل قائمة المؤثرين لاعتماد العميل.', "/app/campaigns/{$c->id}/shortlist"],
            'production' => ['متابعة إنتاج المحتوى', 'تابع المخرجات وحدّث حالاتها.', "/app/campaigns/{$c->id}"],
            'review' => ['اعتماد المحتوى المعلّق', ($m['awaiting_client'] ?? 0) . ' عنصر بانتظار موافقة العميل.', "/app/content"],
            'publishing' => ['التحقق من النشر واعتماد المستحقات', 'تحقّق من روابط النشر واعتمد مستحقات المبدعين.', "/app/payouts"],
            'closure' => ['إغلاق الحملة', 'اكتملت الالتزامات — أغلق الحملة وأصدر التقرير.', "/app/campaigns/{$c->id}"],
            default => ['متابعة الحملة', '', "/app/campaigns/{$c->id}"],
        };

        return [
            'stages' => $stages,
            'current' => $current,
            'current_label' => $labels[$current],
            'progress' => $status === 'completed' ? 100 : (int) round(($curIdx + ($stages[$curIdx]['state'] === 'current' ? 0.5 : 1)) / count($order) * 100),
            'next_action' => ['title' => $next[0], 'hint' => $next[1], 'link' => $next[2]],
            'is_late' => $m['is_late'] ?? false,
        ];
    }
}
