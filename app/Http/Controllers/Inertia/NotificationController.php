<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Communications\Models\{Notification, NotificationDeliveryAttempt};
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مركز إشعارات الوكالة (React/Inertia) — قائمة + غير مقروء + حالة التسليم لكل قناة
 * + تفضيلات القنوات لكل فئة. كل استعلام مقيّد بـuser_id (لا IDOR).
 * حالات المزوّد تُعرَض بلغة بشرية لا تقنية؛ لا أخطاء خام ولا أسرار.
 */
class NotificationController extends Controller
{
    /** فئات الإشعارات الحقيقية (تُطابق سير العمل). */
    public const CATEGORIES = [
        'tasks' => 'المهام والإسناد',
        'campaigns' => 'الحملات',
        'client_approvals' => 'موافقات العملاء',
        'creator_invitations' => 'دعوات المبدعين',
        'content_reviews' => 'مراجعة المحتوى',
        'publishing' => 'النشر',
        'finance' => 'المالية',
        'integrations' => 'التكاملات',
        'system' => 'تنبيهات النظام',
        'general' => 'عام',
    ];

    private const CHANNEL_LABEL = ['in_app' => 'داخل التطبيق', 'email' => 'البريد', 'whatsapp' => 'واتساب', 'sms' => 'رسالة'];

    private const DELIVERY_LABEL = [
        'sent' => 'أُرسل', 'queued' => 'بالطابور', 'delivered' => 'وصل', 'read' => 'قُرئ',
        'failed' => 'فشل', 'skipped' => 'مُتخطّى', 'waiting_for_credentials' => 'بانتظار التهيئة',
    ];
    private const DELIVERY_TONE = [
        'sent' => 'active', 'queued' => 'submitted', 'delivered' => 'active', 'read' => 'completed',
        'failed' => 'changes_requested', 'skipped' => 'draft', 'waiting_for_credentials' => 'under_review',
    ];

    public function index(Request $r): Response
    {
        $uid = $r->user()->id;
        $items = Notification::where('user_id', $uid)->latest()->paginate(20);
        $unread = Notification::where('user_id', $uid)->whereNull('read_at')->count();

        // حالات التسليم لكل قناة لصفحة الإشعارات الحالية (بلا N+1)
        $ids = collect($items->items())->pluck('id');
        $attempts = NotificationDeliveryAttempt::whereIn('notification_id', $ids)->get()
            ->groupBy('notification_id')
            ->map(fn ($g) => $g->map(fn ($a) => [
                'channel' => self::CHANNEL_LABEL[$a->channel] ?? $a->channel,
                'label' => self::DELIVERY_LABEL[$a->status] ?? $a->status,
                'tone' => self::DELIVERY_TONE[$a->status] ?? 'draft',
            ])->values());

        $items->through(fn (Notification $n) => [
            'id' => $n->id, 'title' => $n->title, 'body' => $n->body,
            'category' => self::CATEGORIES[$n->category] ?? $n->category,
            'actionUrl' => $n->action_url, 'read' => $n->read_at !== null,
            'at' => $n->created_at?->format('Y-m-d H:i'),
            'delivery' => $attempts[$n->id] ?? [],
        ]);

        return Inertia::render('Notifications/Index', ['items' => $items, 'unread' => $unread]);
    }

    private function of(Request $r, int $id): Notification
    {
        $n = Notification::where('id', $id)->where('user_id', $r->user()->id)->first();
        abort_unless($n, 404);

        return $n;
    }

    public function read(Request $r, int $notification, NotificationService $svc): RedirectResponse
    {
        $n = $this->of($r, $notification);
        $svc->markRead($n);

        return $n->action_url ? redirect($n->action_url) : back();
    }

    public function readAll(Request $r, NotificationService $svc): RedirectResponse
    {
        $svc->markAllRead(TenantContext::tenantId(), $r->user()->id);

        return back()->with('ok', 'حُدّدت كل الإشعارات كمقروءة.');
    }

    /** تفضيلات القنوات لكل فئة — مصفوفة تُحفظ وتغيّر سلوك التسليم فعليًّا. */
    public function preferences(Request $r): Response
    {
        $uid = $r->user()->id;
        $tid = TenantContext::tenantId();
        $svc = app(NotificationService::class);

        $rows = collect(self::CATEGORIES)->map(function ($label, $key) use ($svc, $tid, $uid) {
            $p = $svc->preference($tid, $uid, $key);
            return ['key' => $key, 'label' => $label, 'in_app' => $p->in_app, 'email' => $p->email, 'whatsapp' => $p->whatsapp, 'sms' => $p->sms];
        })->values();

        return Inertia::render('Notifications/Preferences', [
            'categories' => $rows,
            'channels' => [
                ['key' => 'in_app', 'label' => 'داخل التطبيق', 'always' => true],
                ['key' => 'email', 'label' => 'البريد', 'available' => (bool) config('channels.email.enabled')],
                ['key' => 'whatsapp', 'label' => 'واتساب', 'available' => (bool) config('channels.whatsapp.enabled')],
                ['key' => 'sms', 'label' => 'رسالة SMS', 'available' => (bool) config('channels.sms.enabled')],
            ],
        ]);
    }

    public function updatePreferences(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'category' => 'required|string|in:' . implode(',', array_keys(self::CATEGORIES)),
            'in_app' => 'required|boolean', 'email' => 'required|boolean',
            'whatsapp' => 'required|boolean', 'sms' => 'required|boolean',
        ]);

        app(NotificationService::class)->setPreference(
            TenantContext::tenantId(), $r->user()->id, $data['category'],
            (bool) $data['in_app'], (bool) $data['email'], (bool) $data['sms'], (bool) $data['whatsapp'],
        );

        return back()->with('ok', 'حُدّثت تفضيلات الإشعارات.');
    }
}
