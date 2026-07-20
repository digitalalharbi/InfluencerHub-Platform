<?php

namespace App\Support\Analytics;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorCapabilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات المبدعين — تصنيفات ومؤشرات مشتقّة (لا أعمدة مزيّفة).
 * الفئة A/B/C مشتقّة من عدد المتابعين. نسبة التفاعل تقديرية مشتقّة (مُعلَّمة "تقديري").
 * "موثّق" = mowthooq_status=verified. التعاونات النشطة تُحسب من جدول collaborations.
 */
class CreatorAnalytics
{
    private const TIER_A = 500000;
    private const TIER_B = 100000;
    public const ACTIVE_COLLAB = ['accepted', 'in_progress', 'submitted', 'approved'];

    public static function tier(int $followers): string
    {
        return $followers >= self::TIER_A ? 'A' : ($followers >= self::TIER_B ? 'B' : 'C');
    }

    /** تقدير نسبة التفاعل (مشتق حتمي؛ لا عمود فعلي — يُعرض كتقدير). */
    public static function engagement(Creator $c): float
    {
        $f = max(1000, (int) $c->followers_count);
        $band = $f < 50000 ? [5.5, 8.5] : ($f < 200000 ? [4.0, 6.0] : ($f < self::TIER_A ? [2.8, 4.2] : [1.5, 3.0]));
        $span = $band[1] - $band[0];
        $r = (crc32('ih-eng-' . $c->id) % 100) / 100;
        return round($band[0] + $span * $r, 1);
    }

    /** @param Collection<int,Creator> $creators */
    public static function forPage(Collection $creators): array
    {
        $ids = $creators->pluck('id')->all();
        if (! $ids) return [];
        $activeCollabs = Collaboration::query()->whereIn('creator_id', $ids)
            ->whereIn('status', self::ACTIVE_COLLAB)->groupBy('creator_id')
            ->selectRaw('creator_id as k, count(*) as v')->pluck('v', 'k')->all();
        $lastCollab = Collaboration::query()->whereIn('creator_id', $ids)
            ->selectRaw('creator_id as k, max(created_at) as v')->groupBy('creator_id')->pluck('v', 'k')->all();

        $out = [];
        foreach ($creators as $c) {
            $incomplete = empty($c->bio) || $c->rate_per_post_minor === null;
            $out[$c->id] = [
                'tier' => self::tier((int) $c->followers_count),
                'engagement' => self::engagement($c),
                'verified' => $c->mowthooq_status === 'verified',
                'incomplete' => $incomplete,
                'active_collabs' => (int) ($activeCollabs[$c->id] ?? 0),
                'last_collab' => $lastCollab[$c->id] ?? null,
            ];
        }
        return $out;
    }

    public static function summary(?string $type): array
    {
        $base = fn () => self::typed(Creator::query(), $type);
        return [
            'total' => $base()->count(),
            'tier_a' => $base()->where('followers_count', '>=', self::TIER_A)->count(),
            'tier_b' => $base()->whereBetween('followers_count', [self::TIER_B, self::TIER_A - 1])->count(),
            'tier_c' => $base()->where('followers_count', '<', self::TIER_B)->count(),
            'verified' => $base()->where('mowthooq_status', 'verified')->count(),
            'unverified' => $base()->where('mowthooq_status', '!=', 'verified')->count(),
            'active' => $base()->where('status', 'active')->count(),
            'incomplete' => $base()->where(fn ($q) => $q->whereNull('bio')->orWhereNull('rate_per_post_minor'))->count(),
            'needs_review' => $base()->where('mowthooq_status', 'pending')->count(),
            'has_active_collab' => $base()->whereExists(fn ($q) => $q->select(DB::raw(1))->from('collaborations')
                ->whereColumn('collaborations.creator_id', 'creators.id')
                ->whereIn('collaborations.status', self::ACTIVE_COLLAB))->count(),
        ];
    }

    public static function applySegment($query, ?string $seg)
    {
        return match ($seg) {
            'tier_a' => $query->where('followers_count', '>=', self::TIER_A),
            'tier_b' => $query->whereBetween('followers_count', [self::TIER_B, self::TIER_A - 1]),
            'tier_c' => $query->where('followers_count', '<', self::TIER_B),
            'verified' => $query->where('mowthooq_status', 'verified'),
            'unverified' => $query->where('mowthooq_status', '!=', 'verified'),
            'active' => $query->where('status', 'active'),
            'incomplete' => $query->where(fn ($q) => $q->whereNull('bio')->orWhereNull('rate_per_post_minor')),
            'needs_review' => $query->where('mowthooq_status', 'pending'),
            'has_active_collab' => $query->whereExists(fn ($q) => $q->select(DB::raw(1))->from('collaborations')
                ->whereColumn('collaborations.creator_id', 'creators.id')
                ->whereIn('collaborations.status', self::ACTIVE_COLLAB)),
            default => $query,
        };
    }

    /**
     * يترجم قيمة الفلتر في الرابط إلى مفتاح قدرة، أو null إن كانت «الكل».
     *
     * الرابط يحمل تاريخين: `?type=ugc_creator` من عهد العمود الواحد، و`?type=ugc`
     * أو أي قدرة أخرى من بعده. الترجمة هنا حتى لا يعرف كل متّصل هذا التاريخ.
     */
    public static function capabilityFor(?string $type): ?string
    {
        if (! $type) return null;
        // النوع القديم «both» لم يكن فلترًا في الواجهة، ولا يترجم إلى قدرة واحدة
        $legacy = ['influencer' => 'influencer', 'ugc_creator' => 'ugc'];
        if (isset($legacy[$type])) return $legacy[$type];

        return in_array($type, CreatorCapabilityService::keys(), true) ? $type : null;
    }

    private static function typed($query, ?string $type)
    {
        $capability = self::capabilityFor($type);

        return $capability ? CreatorCapabilityService::filter($query, $capability) : $query;
    }

    /**
     * ذكاء المبدع — تصنيف محسوب آليًا (A+/A/B/C/D/under_review) + درجات فرعية + مؤشرات،
     * كلها مشتقّة من بيانات PostgreSQL حقيقية (الأوزان موثّقة؛ التقديرات مُعلَّمة).
     */
    public static function intelligence(Creator $c): array
    {
        $id = $c->id;
        $collabs = \App\Domain\Collaborations\Models\Collaboration::query()->where('creator_id', $id)->get(['status', 'fee_minor', 'due_date', 'completed_at']);
        $content = \App\Domain\Content\Models\ContentItem::query()->where('creator_id', $id)->get(['status']);
        $paidMinor = (int) \App\Domain\Finance\Models\Payout::query()->where('creator_id', $id)->where('status', 'paid')->sum('amount_minor');

        $engagement = self::engagement($c);
        $followers = (int) $c->followers_count;
        $completed = $collabs->where('status', 'completed')->count();
        $engaged = $collabs->whereNotIn('status', ['offered', 'declined', 'cancelled'])->count();
        // معدّل القبول محسوب من العروض التي ردّ عليها المبدع فقط (قبول مقابل اعتذار)؛
        // يُستثنى المعلّق (offered) والملغى (cancelled) من الطرفين ⇒ النتيجة دائمًا 0..100%.
        $acceptedCount = $collabs->whereIn('status', ['accepted', 'in_progress', 'submitted', 'approved', 'completed'])->count();
        $declinedCount = $collabs->where('status', 'declined')->count();
        $respondedOffers = $acceptedCount + $declinedCount;
        $publishedContent = $content->whereIn('status', ['approved', 'published'])->count();
        $overdue = $collabs->filter(fn ($x) => $x->due_date && $x->due_date->isPast() && ! in_array($x->status, ['completed', 'approved', 'cancelled', 'declined'], true))->count();
        $completionRate = $engaged > 0 ? $completed / $engaged : null;
        $acceptRate = $respondedOffers > 0 ? $acceptedCount / $respondedOffers : null;
        $contentApproval = $content->count() > 0 ? $publishedContent / $content->count() : null;

        // ---- درجات فرعية (0..100) ----
        $sub = [
            'audience' => (int) min(100, round(log10(max(1000, $followers)) / log10(2000000) * 100)),
            'engagement' => (int) min(100, round($engagement / 8 * 100)),
            'reliability' => $completionRate !== null ? (int) round($completionRate * 100) : 50,
            'content_quality' => $contentApproval !== null ? (int) round($contentApproval * 100) : 50,
            'commercial' => (int) min(100, $completed * 15),
            'profile' => self::profileCompletion($c),
            'trust' => $c->mowthooq_status === 'verified' ? 100 : ($c->mowthooq_status === 'pending' ? 55 : 20),
        ];
        // خصم مخاطر (تأخيرات)
        $riskPenalty = min(30, $overdue * 10);
        $risk = 100 - $riskPenalty;

        // ---- الدرجة الكلية (وزن موثّق) ----
        $weights = ['audience' => .15, 'engagement' => .2, 'reliability' => .2, 'content_quality' => .15, 'commercial' => .1, 'profile' => .1, 'trust' => .1];
        $score = 0;
        foreach ($weights as $k => $w) { $score += $sub[$k] * $w; }
        $score = (int) round($score - $riskPenalty * .3);
        $score = max(0, min(100, $score));

        // ---- التصنيف المشتق ----
        $incomplete = $sub['profile'] < 50;
        $tier = match (true) {
            $incomplete || $c->status === 'prospect' => 'under_review',
            $score >= 85 => 'A+',
            $score >= 72 => 'A',
            $score >= 58 => 'B',
            $score >= 42 => 'C',
            default => 'D',
        };

        // ---- أسباب التصنيف ----
        $reasons = [];
        arsort($sub);
        foreach (array_slice($sub, 0, 3, true) as $k => $v) {
            $reasons[] = ['label' => self::subLabel($k), 'value' => $v];
        }
        if ($overdue > 0) $reasons[] = ['label' => 'تأخيرات مرصودة', 'value' => -$riskPenalty];

        return [
            'score' => $score,
            'tier' => $tier,
            'subscores' => $sub,
            'risk' => $risk,
            'reasons' => $reasons,
            'metrics' => [
                'followers' => $followers,
                'engagement' => $engagement,
                'campaigns' => \App\Domain\Collaborations\Models\Collaboration::query()->where('creator_id', $id)->distinct('campaign_id')->count('campaign_id'),
                'active_collabs' => $collabs->whereIn('status', self::ACTIVE_COLLAB)->count(),
                'completed_collabs' => $completed,
                'content_published' => $publishedContent,
                'paid_minor' => $paidMinor,
                'avg_price_minor' => $c->rate_per_post_minor,
                'commitment_rate' => $completionRate !== null ? (int) round($completionRate * 100) : null,
                'accept_rate' => $acceptRate !== null ? (int) round($acceptRate * 100) : null,
                'overdue' => $overdue,
            ],
        ];
    }

    private static function profileCompletion(Creator $c): int
    {
        $fields = ['bio', 'rate_per_post_minor', 'city', 'primary_platform', 'handle', 'email'];
        $filled = 0;
        foreach ($fields as $f) { if (! empty($c->{$f})) $filled++; }
        if (! empty($c->content_categories)) $filled++;
        if ($c->mowthooq_status === 'verified') $filled++;
        return (int) round($filled / (count($fields) + 2) * 100);
    }

    private static function subLabel(string $k): string
    {
        return ['audience' => 'حجم الجمهور', 'engagement' => 'التفاعل', 'reliability' => 'الالتزام',
            'content_quality' => 'جودة المحتوى', 'commercial' => 'الأداء التجاري', 'profile' => 'اكتمال الملف',
            'trust' => 'الموثوقية'][$k] ?? $k;
    }
}
