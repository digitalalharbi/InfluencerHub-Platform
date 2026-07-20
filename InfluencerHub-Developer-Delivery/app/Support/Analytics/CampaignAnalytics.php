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
    public static function readiness(\App\Domain\Campaigns\Models\Campaign $c, array $m): array
    {
        $delivs = $c->deliverables;
        $committed = (int) $delivs->sum(fn ($d) => (int) ($d->fee_minor ?? 0) * (int) $d->quantity);
        $content = $c->contentItems;
        $approvedContent = $content->whereIn('status', ['approved', 'published'])->count();

        $items = [
            ['label' => 'العميل نشِط', 'done' => $c->client && in_array($c->client->status, ['active', 'qualified'], true),
                'hint' => 'حالة العميل يجب أن تكون نشِطة/مؤهّلة', 'link' => $c->client ? "/app/clients/{$c->client_id}" : null],
            ['label' => 'العلامة معتمدة', 'done' => ! $c->brand_id || ($c->brand && $c->brand->status === 'approved'),
                'hint' => 'العلامة بانتظار الاعتماد', 'link' => '/app/brand-reviews'],
            ['label' => 'الميزانية محدّدة', 'done' => (int) $c->budget_minor > 0,
                'hint' => 'حدّد ميزانية الحملة', 'link' => "/app/campaigns/{$c->id}"],
            ['label' => 'مخرجات مُضافة', 'done' => $delivs->count() > 0,
                'hint' => 'أضِف مخرجًا واحدًا على الأقل', 'link' => "/app/campaigns/{$c->id}"],
            ['label' => 'كل مخرج مُسنَد لمبدع', 'done' => $delivs->count() > 0 && $delivs->whereNull('creator_id')->isEmpty(),
                'hint' => 'أسنِد مبدعًا لكل مخرج', 'link' => "/app/campaigns/{$c->id}"],
            ['label' => 'ضمن الميزانية', 'done' => (int) $c->budget_minor === 0 || $committed <= (int) $c->budget_minor,
                'hint' => 'إجمالي الأجور يتجاوز الميزانية', 'link' => "/app/campaigns/{$c->id}"],
            ['label' => 'المحتوى معتمد', 'done' => $content->count() > 0 && $approvedContent === $content->count(),
                'hint' => $content->count() ? ($content->count() - $approvedContent) . ' عنصر لم يُعتمد بعد' : 'لا محتوى بعد', 'link' => '/app/content'],
        ];
        $done = collect($items)->where('done', true)->count();
        return ['items' => $items, 'done' => $done, 'total' => count($items),
            'percent' => (int) round($done / count($items) * 100)];
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
