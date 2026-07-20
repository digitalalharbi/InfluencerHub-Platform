<?php

namespace App\Domain\Automation\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Automation\Models\AutomationLog;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * محرّك SLA داخلي: يمسح طلبات الخدمة المفتوحة ويطبّق قواعد مثبَّتة، مرة واحدة لكل حدث (dedup عبر أعمدة التتبّع):
 *  - تذكير قبل الاستحقاق (خلال نافذة) لمن لم يُذكَّر.
 *  - رصد تجاوز SLA لمن تجاوز ولم يُرصَد، مع إشعار + سجلّ تدقيق.
 * يعمل بتجاوز السياق (وظيفة خلفية صريحة) ويجمّع النتائج لكل مستأجر.
 */
class SlaEngineService
{
    /** نافذة التذكير الافتراضية قبل الاستحقاق (ساعات). */
    public function __construct(private NotificationService $notifications, private int $reminderWindowHours = 12) {}

    /** يمسح كل المستأجرين (وظيفة مجدولة). يعيد ملخّص العدّ. */
    public function scan(): array
    {
        return TenantContext::withBypass(function () {
            $open = ServiceRequest::withoutGlobalScopes()
                ->whereIn('status', ServiceRequest::OPEN_STATUSES)
                ->whereNotNull('due_at')
                ->get();
            $breaches = 0; $reminders = 0;
            foreach ($open as $sr) {
                if ($this->handleBreach($sr)) $breaches++;
                elseif ($this->handleReminder($sr)) $reminders++;
            }
            return ['scanned' => $open->count(), 'breaches' => $breaches, 'reminders' => $reminders];
        });
    }

    private function handleBreach(ServiceRequest $sr): bool
    {
        if ($sr->sla_breached_at !== null) return false;          // رُصد سابقًا (dedup)
        if ($sr->due_at->isFuture()) return false;                 // لم يتجاوز بعد
        $sr->forceFill(['sla_breached_at' => now()])->saveQuietly();
        $this->log($sr, 'sla.breach', 'تجاوز الاستحقاق ' . $sr->due_at->toDateTimeString());
        AuditLogger::log('sla.breach', $sr, ['request' => $sr->request_number], $sr->tenant_id, null);
        $this->notifyOwners($sr, 'تجاوز SLA لطلب: ' . $sr->title, 'تجاوز الطلب موعد الاستحقاق ولم يُنجَز بعد.');
        return true;
    }

    private function handleReminder(ServiceRequest $sr): bool
    {
        if ($sr->sla_reminded_at !== null) return false;          // ذُكّر سابقًا (dedup)
        if ($sr->due_at->isPast()) return false;                   // متأخّر أصلًا → لا تذكير
        if (abs($sr->due_at->diffInHours(now())) > $this->reminderWindowHours) return false; // خارج النافذة
        $sr->forceFill(['sla_reminded_at' => now()])->saveQuietly();
        $this->log($sr, 'sla.reminder', 'تذكير قبل الاستحقاق ' . $sr->due_at->toDateTimeString());
        $this->notifyOwners($sr, 'تذكير SLA لطلب: ' . $sr->title, 'يقترب موعد استحقاق الطلب.');
        return true;
    }

    /** يُشعر المُسنَد إليه إن وُجد، وإلا مديري الوكالة في نفس المستأجر. */
    private function notifyOwners(ServiceRequest $sr, string $title, string $body): void
    {
        $url = "/app/service-requests/{$sr->id}";
        if ($sr->assigned_to) {
            $this->notifications->notify($sr->tenant_id, $sr->assigned_to, 'sla.alert', 'general', $title, $body, $url, ['request_id' => $sr->id], $sr);
            return;
        }
        $adminIds = OrganizationMembership::withoutGlobalScopes()
            ->where('tenant_id', $sr->tenant_id)->where('status', 'active')
            ->whereIn('role', ['agency_admin', 'operations_manager'])
            ->pluck('user_id')->unique()->all();
        foreach ($adminIds as $uid) {
            $this->notifications->notify($sr->tenant_id, (int) $uid, 'sla.alert', 'general', $title, $body, $url, ['request_id' => $sr->id], $sr);
        }
    }

    private function log(ServiceRequest $sr, string $rule, string $detail): void
    {
        AutomationLog::create([
            'tenant_id' => $sr->tenant_id, 'rule' => $rule, 'subject_type' => ServiceRequest::class,
            'subject_id' => $sr->id, 'detail' => $detail, 'created_at' => now(),
        ]);
    }
}
