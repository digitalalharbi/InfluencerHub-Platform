<?php

namespace App\Http\Controllers\Inertia\Creator;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * إشعارات المبدع (React/Inertia).
 * كل استعلام مقيّد بـuser_id داخل مستأجر المبدع — لا يرى أحد إشعار غيره.
 */
class NotificationController extends Controller
{
    public function index(Request $r): Response
    {
        $c = $r->attributes->get('creator');
        try {
            $items = Notification::where('user_id', $r->user()->id)->latest()->paginate(20);
            $unread = Notification::where('user_id', $r->user()->id)->whereNull('read_at')->count();
        } finally {
        }

        $items->through(fn (Notification $n) => [
            'id' => $n->id, 'title' => $n->title, 'body' => $n->body,
            'category' => $n->category, 'actionUrl' => $n->action_url,
            'read' => $n->read_at !== null,
            'at' => $n->created_at?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('CreatorPortal/Notifications', [
            'items' => $items,
            'unread' => $unread,
        ]);
    }

    /** الإشعار يجب أن يخصّ هذا المستخدم — منع IDOR. */
    private function of(Request $r, int $id): Notification
    {
        $n = TenantContext::withTenant(
            $r->attributes->get('creator')->tenant_id,
            fn () => Notification::where('id', $id)->where('user_id', $r->user()->id)->first(),
        );
        abort_unless($n, 404);

        return $n;
    }

    /** القراءة تتبع رابط الإشعار إن وُجد — كما في نسخة Blade. */
    public function read(Request $r, int $notification, NotificationService $svc): RedirectResponse
    {
        $n = $this->of($r, $notification);
        $svc->markRead($n);

        return $n->action_url ? redirect($n->action_url) : back();
    }

    public function readAll(Request $r, NotificationService $svc): RedirectResponse
    {
        $c = $r->attributes->get('creator');
        $svc->markAllRead($c->tenant_id, $r->user()->id);

        return back()->with('ok', 'حُدّدت كل الإشعارات كمقروءة.');
    }
}
