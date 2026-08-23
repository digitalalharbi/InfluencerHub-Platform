<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\{Notification, NotificationPreference};
use App\Domain\Communications\Services\DeliveryDispatcher;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * إنشاء الإشعارات وتوصيلها بشكل محايد للمزوّد عبر طبقة قنوات (DeliveryDispatcher).
 * in_app يُسلَّم فورًا؛ القنوات الخارجية (email/whatsapp/sms) تُرسِل فعليًّا إن كانت
 * مهيّأة، وإلّا تُسجَّل بحالة صادقة (waiting_for_credentials) — لا تسليم وهمي.
 */
class NotificationService
{
    public function __construct(private DeliveryDispatcher $dispatcher) {}

    /** ينشئ إشعارًا لمستخدم واحد ويوزّع تسليمه عبر القنوات حسب التفضيلات. */
    public function notify(int $tenantId, int $userId, string $type, string $category, string $title, ?string $body = null, ?string $actionUrl = null, array $data = [], ?Model $subject = null): Notification
    {
        return $this->withTenant($tenantId, function () use ($tenantId, $userId, $type, $category, $title, $body, $actionUrl, $data, $subject) {
            $pref = $this->preference($tenantId, $userId, $category);

            $n = Notification::create([
                'tenant_id' => $tenantId, 'user_id' => $userId, 'type' => $type, 'category' => $category,
                'title' => $title, 'body' => $body, 'action_url' => $actionUrl, 'data' => $data ?: null,
                'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(),
            ]);

            $this->dispatcher->dispatch($n, $pref);

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
            ['in_app' => true, 'email' => false, 'whatsapp' => false, 'sms' => false]
        ));
    }

    /** يحدّث تفضيلات فئة (upsert). القنوات غير المُمرَّرة تبقى كما هي. */
    public function setPreference(int $tenantId, int $userId, string $category, bool $inApp, bool $email, bool $sms, ?bool $whatsapp = null): NotificationPreference
    {
        return $this->withTenant($tenantId, function () use ($tenantId, $userId, $category, $inApp, $email, $sms, $whatsapp) {
            $values = ['in_app' => $inApp, 'email' => $email, 'sms' => $sms];
            if ($whatsapp !== null) {
                $values['whatsapp'] = $whatsapp;
            }

            return NotificationPreference::updateOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $userId, 'category' => $category],
                $values
            );
        });
    }
}
