<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Communications\Models\NotificationPreference;
use App\Domain\Communications\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * أمان الحساب المشترك بين كل البوابات: تفضيلات الإشعارات، تغيير كلمة المرور،
 * والجلسات النشطة.
 *
 * سبب المشاركة: هذه ليست ميزة بوابة بل حقّ كل مستخدم مُصادَق. كانت متاحة
 * لبوابة العميل وحدها، فلم يكن بوسع المبدع ولا الشريك ولا موظّف الوكالة
 * تغيير كلمة مروره أو إنهاء جلسة مسروقة.
 *
 * المستضيف يوفّر `securityTenantId()` لأن نطاق التفضيلات هو المستأجر.
 */
trait ManagesAccountSecurity
{
    abstract protected function securityTenantId(Request $r): int;

    /** @return array<string,mixed> */
    protected function securityPayload(Request $r, NotificationService $svc): array
    {
        $tenantId = $this->securityTenantId($r);
        $prefs = [];
        foreach (array_keys(NotificationPreference::CATEGORIES) as $cat) {
            $p = $svc->preference($tenantId, $r->user()->id, $cat);
            $prefs[$cat] = [
                'in_app' => (bool) ($p->in_app ?? true),
                'email' => (bool) ($p->email ?? false),
                'sms' => (bool) ($p->sms ?? false),
            ];
        }

        return [
            'prefs' => $prefs,
            'categories' => NotificationPreference::CATEGORIES,
            'sessions' => $this->activeSessions($r),
            'twoFactorEnabled' => (bool) $r->user()->two_factor_confirmed_at,
        ];
    }

    public function updateNotificationPrefs(Request $r, NotificationService $svc): RedirectResponse
    {
        $tenantId = $this->securityTenantId($r);
        $on = $r->input('prefs', []);
        foreach (array_keys(NotificationPreference::CATEGORIES) as $cat) {
            $svc->setPreference($tenantId, $r->user()->id, $cat,
                (bool) data_get($on, "$cat.in_app", true),
                (bool) data_get($on, "$cat.email"),
                (bool) data_get($on, "$cat.sms"));
        }

        return back()->with('ok', 'حُفظت تفضيلات الإشعارات.');
    }

    public function changePassword(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['current_password' => 'كلمة المرور الحالية', 'password' => 'كلمة المرور الجديدة']);

        $r->user()->update(['password' => Hash::make($data['password']), 'must_change_password' => false]);
        // تغيير كلمة المرور يُبطل الجلسات الأخرى — الجلسة المسروقة لا تبقى صالحة
        $this->purgeOtherSessions($r);

        return back()->with('ok', 'حُدّثت كلمة المرور، وأُبطلت الجلسات الأخرى.');
    }

    public function revokeOtherSessions(Request $r): RedirectResponse
    {
        $this->purgeOtherSessions($r);

        return back()->with('ok', 'أُنهيت جميع الجلسات الأخرى.');
    }

    /**
     * يُنفَّذ دائمًا بلا نظر إلى سائق الجلسات: هذا مسار أمني، والحارس هنا
     * كان يجعله يصمت بلا أثر. جدول `sessions` موجود بالهجرة في كل الأحوال.
     */
    private function purgeOtherSessions(Request $r): void
    {
        DB::table('sessions')->where('user_id', $r->user()->id)
            ->where('id', '!=', $r->session()->getId())->delete();
    }

    /** @return array<int,array<string,mixed>> */
    private function activeSessions(Request $r): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table('sessions')->where('user_id', $r->user()->id)
            ->orderByDesc('last_activity')->limit(10)->get()
            ->map(fn ($s) => [
                'current' => $s->id === $r->session()->getId(),
                'ip' => $s->ip_address,
                'agent' => Str::limit((string) $s->user_agent, 60),
                'lastActivity' => $s->last_activity ? date('Y-m-d H:i', (int) $s->last_activity) : null,
            ])->all();
    }
}
