<?php

namespace App\Domain\Content\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * تذكير موعد نشر المحتوى — تذكير حقيقيّ مبنيّ على scheduled_at الفعليّة (لا موعد مُختلَق).
 *
 * يُشعِر مرّة واحدة حين يقترب موعد نشر محتوى مُجدوَل (أو يتجاوزه) ولم يُنشَر بعد، ويحفظ
 * publish_reminded_at لمنع التكرار (لا تذكير ثانٍ لنفس المحتوى). المستقبِلون: صاحب
 * الحساب على المبدع (هو من ينشر) + صاحب الحملة كسند. يعمل مجدولًا (content:scan-publishing).
 *
 * لا يخترع تصعيدًا: تذكير أوّليّ صادق وآمن من التكرار فقط، على غرار تذكير الفاتورة وSLA.
 */
final class ContentPublishReminderService
{
    /** نافذة التذكير قبل موعد النشر (ساعات) — التذكير يُرسَل داخلها أو بعد فوات الموعد. */
    public function __construct(private NotificationService $notifications, private int $windowHours = 24) {}

    /** يمسح كل المستأجرين (تجاوز النطاق للقراءة الإداريّة) ويُذكّر بالمُجدوَل المقترب. */
    public function scan(): array
    {
        return TenantContext::withBypass(function () {
            $threshold = now()->addHours($this->windowHours);

            $due = ContentItem::query()
                ->where('status', 'scheduled')          // مُجدوَل للنشر ولم يُنشَر بعد
                ->whereNotNull('scheduled_at')
                ->whereNull('published_at')
                ->whereNull('publish_reminded_at')      // لم يُذكَّر بعد (منع التكرار)
                ->where('scheduled_at', '<=', $threshold) // اقترب الموعد (أو فات)
                ->get();

            $notified = 0;
            foreach ($due as $item) {
                $item->forceFill(['publish_reminded_at' => now()])->saveQuietly(); // العلامة أوّلًا: إعادة التشغيل آمنة
                if ($this->notifyOwners($item)) {
                    $notified++;
                }
            }

            return ['scanned' => $due->count(), 'notified' => $notified];
        });
    }

    /** يُشعِر صاحب الحساب على المبدع + صاحب الحملة. يُرجع true إن أُرسل لأحد. */
    private function notifyOwners(ContentItem $item): bool
    {
        $recipients = [];
        if ($item->creator_id && ($uid = Creator::find($item->creator_id)?->user_id)) {
            $recipients[] = (int) $uid;
        }
        if ($item->campaign_id && ($owner = Campaign::find($item->campaign_id)?->created_by)) {
            $recipients[] = (int) $owner;
        }
        if ($item->created_by) {
            $recipients[] = (int) $item->created_by;
        }
        $recipients = array_values(array_unique(array_filter($recipients)));

        if (empty($recipients)) {
            return false;
        }

        $overdue = $item->scheduled_at?->isPast() ?? false;
        $body = $overdue
            ? 'فات موعد نشر هذا المحتوى ولم يُسجَّل نشره بعد. انشره وأثبت النشر.'
            : 'يقترب موعد نشر هذا المحتوى المُجدوَل. تأكّد من جاهزيّته للنشر في موعده.';

        $this->notifications->notifyMany(
            $item->tenant_id,
            $recipients,
            'content.publish_reminder',
            'content',
            "تذكير نشر: {$item->title}",
            $body,
            "/app/content/{$item->id}",
            [
                'objects' => [['type' => 'content', 'name' => $item->title]],
                'status' => $overdue ? 'فات الموعد' : 'مُجدوَل',
                'due' => $item->scheduled_at?->format('Y-m-d H:i'),
                'priority' => $overdue ? 'high' : 'normal',
                'cta_label' => 'عرض المحتوى',
                'content_id' => $item->id,
            ],
            $item,
        );

        AuditLogger::log('content.publish_reminded', $item, ['scheduled_at' => $item->scheduled_at?->format('Y-m-d H:i')], $item->tenant_id, null);

        return true;
    }
}
