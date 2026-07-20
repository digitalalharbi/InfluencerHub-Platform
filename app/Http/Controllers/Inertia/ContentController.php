<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Content\Models\ContentItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * طابور المحتوى والموافقات (React/Inertia) — أغنى من نسخة Blade: KPIs + شرائح + بحث.
 * Policy(viewAny)، معزول بالمستأجر.
 */
class ContentController extends Controller
{
    private const TYPE_LABEL = ['post' => 'منشور', 'story' => 'ستوري', 'reel' => 'ريل', 'video' => 'فيديو', 'ugc' => 'UGC'];

    public function index(Request $r): Response
    {
        $this->authorize('viewAny', ContentItem::class);

        $q = ContentItem::query()->with('creator', 'client', 'campaign')->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('title', 'ilike', "%{$s}%")->orWhere('content_number', 'ilike', "%{$s}%")
                ->orWhereHas('creator', fn ($c) => $c->where('display_name', 'ilike', "%{$s}%")));
        }
        if ($v = $r->query('type')) $q->where('type', $v);
        // `?status=` كانت فلتر نسخة Blade — تُقبل كمرادف لـ`seg` حتى لا تنكسر روابط محفوظة
        $seg = $r->query('seg') ?: $r->query('status');
        match ($seg) {
            'agency_review', 'client_review', 'changes_requested', 'approved', 'scheduled', 'published', 'draft', 'rejected'
                => $q->where('status', $seg),
            default => null,
        };

        $items = $q->paginate(20)->withQueryString();
        $items->through(fn (ContentItem $c) => [
            'id' => $c->id,
            'number' => $c->content_number,
            'title' => $c->title,
            'creator' => $c->creator?->display_name,
            'campaign' => $c->campaign?->name,
            'type' => self::TYPE_LABEL[$c->type] ?? $c->type,
            'platform' => $c->platform,
            'version' => (int) $c->version,
            'status' => $c->status,
            'statusLabel' => __('statuses.' . $c->status),
            'statusTone' => __('statuses.tone.' . $c->status),
            'needsReview' => $c->status === 'agency_review',
            'mediaUrl' => $c->media_url,
            'scheduledAt' => $c->scheduled_at?->format('Y-m-d'),
            'publishedAt' => $c->published_at?->format('Y-m-d'),
            'needsAction' => in_array($c->status, ['agency_review', 'client_review', 'changes_requested'], true),
        ]);

        // عدّادات الحالات في استعلام تجميعي واحد بدل استعلام لكل حالة
        $byStatus = ContentItem::query()->groupBy('status')->selectRaw('status, count(*) as c')->pluck('c', 'status');
        $count = fn (string $st) => (int) ($byStatus[$st] ?? 0);
        return Inertia::render('Content/Index', [
            'items' => $items,
            'filters' => array_filter(['q' => $r->query('q'), 'type' => $r->query('type'), 'seg' => $seg]),
            'typeLabels' => self::TYPE_LABEL,
            'summary' => [
                'total' => (int) $byStatus->sum(),
                'agency_review' => $count('agency_review'),
                'client_review' => $count('client_review'),
                'changes_requested' => $count('changes_requested'),
                'approved' => $count('approved'),
                'scheduled' => $count('scheduled'),
                'published' => $count('published'),
                'draft' => $count('draft'),
                'rejected' => $count('rejected'),
            ],
        ]);
    }
}
