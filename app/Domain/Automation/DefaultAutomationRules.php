<?php

namespace App\Domain\Automation;

use App\Domain\Automation\Models\AutomationRule;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * قواعد أتمتة افتراضية احترافية — مجموعة صغيرة عالية القيمة، لا عشرات مربكة.
 * تُثبَّت لكل مستأجر (idempotent بالمفتاح)، ولا تُكرّر إشعارات مباشرة قائمة:
 * كل قاعدة تُخطِر مستفيدًا لا يُخطَر أصلًا، أو تُضيف قيمة جديدة.
 */
class DefaultAutomationRules
{
    /** @return array<int,array<string,mixed>> */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'sys.request.created.confirm',
                'name' => 'تأكيد استلام الطلب',
                'trigger' => 'service_request.created',
                'conditions' => [],
                'actions' => [[
                    'type' => 'notify', 'to' => 'requested_by', 'category' => 'tasks',
                    'notification_type' => 'automation.request_received',
                    'title' => 'استُلم طلبك {{number}}', 'body' => '{{title}}',
                    'action_url' => '/app/service-requests/{{id}}',
                ]],
            ],
            [
                // اعتماد العميل يُخطر المبدع مباشرة؛ الأتمتة تُخطر «صاحب الحملة» (مستفيد جديد).
                'key' => 'sys.content.approved.notify_owner',
                'name' => 'إبلاغ صاحب الحملة باعتماد المحتوى',
                'trigger' => 'content.approved',
                'conditions' => [],
                'actions' => [[
                    'type' => 'notify', 'to' => 'campaign_owner_id', 'category' => 'content_reviews',
                    'notification_type' => 'automation.content_approved',
                    'title' => 'اعتُمد محتوى وجاهز للجدولة', 'body' => '{{title}}',
                    'action_url' => '/app/content/{{content_id}}',
                ]],
            ],
            [
                'key' => 'sys.creator.declined.alert',
                'name' => 'تنبيه اعتذار مبدع',
                'trigger' => 'creator.declined',
                'conditions' => [],
                'actions' => [[
                    'type' => 'notify', 'to' => 'campaign_owner_id', 'category' => 'campaigns',
                    'notification_type' => 'automation.creator_declined',
                    'title' => 'اعتذر مبدع عن التعاون — يلزم بديل', 'body' => '{{creator_name}}',
                    'action_url' => '/app/campaigns/{{campaign_id}}',
                ]],
            ],
        ];
    }

    /** يثبّت القواعد الافتراضية للمستأجر إن غابت (لا يمسّ ما عدّله المستخدم). */
    public function ensure(int $tenantId): void
    {
        TenantContext::withTenant($tenantId, function () use ($tenantId) {
            foreach (self::definitions() as $def) {
                AutomationRule::firstOrCreate(
                    ['tenant_id' => $tenantId, 'key' => $def['key']],
                    [
                        'name' => $def['name'], 'trigger' => $def['trigger'],
                        'conditions' => $def['conditions'], 'actions' => $def['actions'],
                        'enabled' => true, 'priority' => 100, 'is_system' => true,
                    ],
                );
            }
        });
    }
}
