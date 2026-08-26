<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\AdminPool\Models\PoolRecommendation;
use App\Domain\Communications\Enums\NotificationCategory;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ترشيحات المؤثرين في بوابة العميل — يقرّ العميل (قبول/رفض) ما حوّله مدير النظام.
 *
 * معزولة على العميل النشِط (EnsureClientMember) ونطاق المستأجر. نسخة مستقلّة عن
 * القاعدة وبلا جوّال — العميل يرى الاسم والمنصّة والمتابعين والسعر المعروض فقط.
 */
class RecommendationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $r): Response
    {
        /** @var Client $c */
        $c = $r->attributes->get('activeClient');

        $base = PoolRecommendation::where('client_id', $c->id);
        $counts = [
            'pending' => (clone $base)->where('status', 'recommended')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'total' => (clone $base)->count(),
        ];

        // المعلّق أولًا، ثم الأحدث
        $items = (clone $base)
            ->orderByRaw("case status when 'recommended' then 0 when 'approved' then 1 else 2 end")
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (PoolRecommendation $x) => [
                'id' => $x->id,
                'name' => $x->name,
                'platformLabel' => PoolCreator::PLATFORM_LABELS[$x->platform] ?? $x->platform,
                'accountUrl' => $x->account_url,
                'followers' => $x->followers,
                'categories' => $x->categories ?? [],
                'priceMinor' => $x->price_minor,
                'region' => $x->region,
                'city' => $x->city,
                'sourceType' => $x->source_type,
                'status' => $x->status,
                'reason' => $x->decision_reason,
                'decidedAt' => $x->decided_at?->format('Y-m-d'),
            ]);

        return Inertia::render('ClientPortal/Recommendations/Index', [
            'clientName' => $c->display_name,
            'items' => $items,
            'counts' => $counts,
        ]);
    }

    public function decision(Request $r, int $recommendation): RedirectResponse
    {
        /** @var Client $c */
        $c = $r->attributes->get('activeClient');
        $data = $r->validate([
            'decision' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|max:500',
        ], [], ['decision' => 'القرار', 'reason' => 'السبب']);

        // ضمن نطاق العميل النشِط فقط
        $rec = PoolRecommendation::where('client_id', $c->id)->whereKey($recommendation)->first();
        abort_unless($rec, 404);

        $rec->update([
            'status' => $data['decision'],
            'decision_reason' => $data['reason'] ?? null,
            'decided_at' => now(),
        ]);

        $this->announceRecommendationDecision($rec, $c->display_name, $data['decision'], $data['reason'] ?? null);

        return back()->with('ok', $data['decision'] === 'approved' ? 'اعتمدت المؤثر المرشّح.' : 'رفضت المؤثر المرشّح.');
    }

    /**
     * يُشعِر فريق الوكالة (agency_admin) في مستأجر الترشيح بقرار العميل عبر نظام الإشعارات
     * المشترك — الوكالة هي المالك التشغيلي للعميل والطرف الوحيد المضمون رؤيته للإشعار داخل
     * نطاق المستأجر (مالك المنصّة عابر للمستأجرين وبلا مركز إشعارات، فلا يُرسَل إليه). لا
     * مصطلحات تقنية في النص — أسماء أعمال فقط (العميل/المؤثّر/الحملة).
     */
    private function announceRecommendationDecision(PoolRecommendation $rec, string $clientName, string $decision, ?string $reason): void
    {
        $recipients = OrganizationMembership::where('tenant_id', $rec->tenant_id)
            ->where('role', 'agency_admin')
            ->where('status', 'active')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        if (empty($recipients)) {
            return; // لا فريق وكالة نشِط — لا مستقبِل مضمون الرؤية، فلا نُصدِر إشعارًا خفيًّا
        }

        $verb = $decision === 'approved' ? 'اعتمد' : 'رفض';
        $title = "{$verb} العميل المؤثّر المرشّح: {$rec->name}";
        $body = $decision === 'approved'
            ? "العميل «{$clientName}» جاهز لإدراج المؤثّر ضمن حملته."
            : "العميل «{$clientName}» طلب بديلًا عن هذا المؤثّر.";
        if ($decision === 'rejected' && filled($reason)) {
            $body .= " السبب: {$reason}";
        }

        // رابط آمن فقط حين يرتبط الترشيح بحملة فعلية — نتجنّب روابط عميقة إلى صفحة غير موجودة
        $actionUrl = $rec->campaign_id ? "/app/campaigns/{$rec->campaign_id}" : null;
        $data = [
            'objects' => [
                ['type' => 'creator', 'name' => $rec->name],
                ['type' => 'client', 'name' => $clientName],
            ],
        ];
        if ($actionUrl) {
            $data['cta_label'] = 'عرض الحملة';
        }

        $this->notifications->notifyMany(
            $rec->tenant_id,
            $recipients,
            "pool_recommendation.{$decision}",
            NotificationCategory::Creators->value,
            $title,
            $body,
            $actionUrl,
            $data,
            $rec,
        );
    }
}
