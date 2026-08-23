<?php

namespace App\Domain\Automation\Actions;

use App\Domain\Communications\Services\NotificationService;

/**
 * إجراء إشعار — يُشعر مستخدمًا من سياق المحفّز (config.to = مفتاح سياق يحمل user_id).
 * العنوان/النص يُقوْلَبان من السياق بـ{{key}}. يمرّ عبر طبقة القنوات (in_app + المُفعّلة).
 */
class NotifyAction implements AutomationAction
{
    public function __construct(private NotificationService $notifications) {}

    public function type(): string { return 'notify'; }

    public function execute(array $config, array $context, int $tenantId): array
    {
        $userId = (int) ($context[$config['to'] ?? 'user_id'] ?? 0);
        if ($userId <= 0) {
            return ['skipped' => 'no recipient'];
        }

        $title = $this->template($config['title'] ?? 'تنبيه', $context);
        $body = isset($config['body']) ? $this->template($config['body'], $context) : null;

        $this->notifications->notify(
            $tenantId, $userId,
            $config['notification_type'] ?? 'automation.notice',
            $config['category'] ?? 'general',
            $title, $body,
            isset($config['action_url']) ? $this->template($config['action_url'], $context) : null,
            ['automation' => true],
        );

        return ['notified' => $userId, 'title' => $title];
    }

    private function template(string $s, array $context): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($context[$m[1]] ?? ''), $s) ?? $s;
    }
}
