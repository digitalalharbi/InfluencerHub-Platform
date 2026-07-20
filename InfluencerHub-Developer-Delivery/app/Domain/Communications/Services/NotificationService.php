<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\{Notification, NotificationDeliveryAttempt, NotificationPreference};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * إنشاء الإشعارات وتوصيلها بشكل محايد للمزوّد.
 * in_app يُسلَّم فورًا؛ email/sms تُسجَّل كمحاولة بحالة صادقة (waiting_for_credentials)
 * حتى تُربط مزوّدات حقيقية لاحقًا — لا ادّعاء تسليم زائف.
 */
class NotificationService
{
    /** ينشئ إشعارًا لمستخدم واحد ويسجّل محاولات التسليم حسب التفضيلات. */
    public function notify(int $tenantId, int $userId, string $type, string $category, string $title, ?string $body = null, ?string $actionUrl = null, array $data = [], ?Model $subject = null): Notification
    {
        return $this->withTenant($tenantId, function () use ($tenantId, $userId, $type, $category, $title, $body, $actionUrl, $data, $subject) {
            $pref = $this->preference($tenantId, $userId, $category);

            $n = Notification::create([
                'tenant_id' => $tenantId, 'user_id' => $userId, 'type' => $type, 'category' => $category,
                'title' => $title, 'body' => $body, 'action_url' => $actionUrl, 'data' => $data ?: null,
                'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(),
            ]);

            // in_app: الإشعار نفسه هو التسليم داخل التطبيق.
            $this->recordAttempt($n, 'in_app', $pref->in_app ? 'sent' : 'skipped', $pref->in_app ? null : 'معطّل في التفضيلات');
            // email/sms: لا مزوّد مربوط بعد — حالة صادقة، لا تسليم وهمي.
            if ($pref->email) $this->recordAttempt($n, 'email', 'waiting_for_credentials', 'لا مزوّد بريد مربوط');
            if ($pref->sms)   $this->recordAttempt($n, 'sms', 'waiting_for_credentials', 'لا مزوّد SMS مربوط');

            return $n;
        });
    }

    /**
     * ينفّذ ضمن سياق مستأجر محدّد ثم يستعيد السياق السابق كاملًا.
     *
     * كانت هنا نسخة يدوية تُظلّل `TenantContext::withTenant` وتستعيد ناقصًا:
     * إن كان المتصل **متجاوِزًا** أعادت التجاوز وحده وأسقطت المستأجر والمؤسسة
     * وورشة العمل. والإشعار يُنشأ في نهاية سير عمل — فالسياق الضائع يظهر بعده
     * بمسافة، في استعلام آخر يعود فارغًا بلا خطأ.
     */
    private function withTenant(int $tenantId, callable $fn)
    {
        return TenantContext::withTenant($tenantId, $fn);
    }

    /** ينشئ إشعارًا لعدة مستخدمين (مثل كل أعضاء العميل النشِطين). يعيد الإشعارات المُنشأة. */
    public function notifyMany(int $tenantId, array $userIds, string $type, string $category, string $title, ?string $body = null, ?string $actionUrl = null, array $data = [], ?Model $subject = null): Collection
    {
        return collect(array_unique($userIds))->map(fn ($uid) => $this->notify($tenantId, (int) $uid, $type, $category, $title, $body, $actionUrl, $data, $subject));
    }

    public function markRead(Notification $n): void
    {
        if (! $n->isRead()) $n->update(['read_at' => now()]);
    }

    public function markAllRead(int $tenantId, int $userId): int
    {
        return $this->withTenant($tenantId, fn () => Notification::where('user_id', $userId)->whereNull('read_at')->update(['read_at' => now()]));
    }

    public function unreadCount(int $tenantId, int $userId): int
    {
        return $this->withTenant($tenantId, fn () => Notification::where('user_id', $userId)->whereNull('read_at')->count());
    }

    /** تفضيلات المستخدم لفئة معيّنة (تُنشأ بالقيَم الافتراضية عند الغياب). */
    public function preference(int $tenantId, int $userId, string $category): NotificationPreference
    {
        return $this->withTenant($tenantId, fn () => NotificationPreference::firstOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'category' => $category],
            ['in_app' => true, 'email' => false, 'sms' => false]
        ));
    }

    /** يحدّث تفضيلات فئة (upsert). */
    public function setPreference(int $tenantId, int $userId, string $category, bool $inApp, bool $email, bool $sms): NotificationPreference
    {
        return $this->withTenant($tenantId, fn () => NotificationPreference::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'category' => $category],
            ['in_app' => $inApp, 'email' => $email, 'sms' => $sms]
        ));
    }

    private function recordAttempt(Notification $n, string $channel, string $status, ?string $detail): void
    {
        NotificationDeliveryAttempt::create([
            'tenant_id' => $n->tenant_id, 'notification_id' => $n->id,
            'channel' => $channel, 'status' => $status, 'detail' => $detail, 'attempted_at' => now(),
        ]);
    }
}
