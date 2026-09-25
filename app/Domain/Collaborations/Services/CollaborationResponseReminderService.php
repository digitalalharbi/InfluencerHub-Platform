<?php

namespace App\Domain\Collaborations\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * تذكير ردّ المؤثر على عرض التعاون — تذكير حقيقيّ مبنيّ على offered_at الفعليّة.
 *
 * إن بقي العرض «offered» (بلا ردّ) بعد ٤٨ ساعة من عرضه، يُذكَّر المؤثر مرّة واحدة
 * (وصاحب العرض للعلم)، وتُحفظ response_reminded_at لمنع التكرار. يعمل مجدولًا
 * (collaborations:scan-pending-response) على غرار تذكير الفاتورة وSLA والنشر.
 *
 * لا يخترع موعدًا ولا تصعيدًا: نافذة الـ٤٨ ساعة هي ما نصّ عليه المطلب، وتذكير أوّليّ
 * صادق وآمن من التكرار فقط.
 */
final class CollaborationResponseReminderService
{
    /** نافذة انتظار الردّ قبل التذكير (ساعات) — المطلب: ٤٨ ساعة. */
    public function __construct(private NotificationService $notifications, private int $responseWindowHours = 48) {}

    /** يمسح كل المستأجرين (تجاوز النطاق للقراءة الإداريّة) ويُذكّر بالعروض المعلّقة. */
    public function scan(): array
    {
        return TenantContext::withBypass(function () {
            $cutoff = now()->subHours($this->responseWindowHours);

            $pending = Collaboration::query()
                ->where('status', 'offered')            // معروض بانتظار ردّ المؤثر
                ->whereNotNull('offered_at')
                ->whereNull('responded_at')
                ->whereNull('response_reminded_at')     // لم يُذكَّر بعد (منع التكرار)
                ->where('offered_at', '<=', $cutoff)    // مضت نافذة الـ٤٨ ساعة
                ->get();

            $notified = 0;
            foreach ($pending as $c) {
                $c->forceFill(['response_reminded_at' => now()])->saveQuietly(); // العلامة أوّلًا: إعادة التشغيل آمنة
                if ($this->remind($c)) {
                    $notified++;
                }
            }

            return ['scanned' => $pending->count(), 'notified' => $notified];
        });
    }

    /** يُذكّر المؤثر (بوابته) وصاحب العرض (بوابة الوكالة). يُرجع true إن أُرسل لأحد. */
    private function remind(Collaboration $c): bool
    {
        $sent = false;
        $meta = ['objects' => [['type' => 'collaboration', 'name' => $c->title]], 'priority' => 'high', 'collaboration_id' => $c->id];

        if ($c->creator_id && ($uid = Creator::find($c->creator_id)?->user_id)) {
            $this->notifications->notify(
                $c->tenant_id, (int) $uid, 'collaboration.response_reminder', 'creators',
                "تذكير: عرض تعاون بانتظار ردّك — {$c->title}",
                'مضى وقتٌ على عرض التعاون ولم تردّ بعد. اقبله أو اعتذر عنه حتى تمضي الحملة.',
                '/creator/collaborations', $meta + ['cta_label' => 'عرض العرض'], $c,
            );
            $sent = true;
        }

        if ($c->created_by) {
            $this->notifications->notify(
                $c->tenant_id, (int) $c->created_by, 'collaboration.response_reminder', 'creators',
                "عرض تعاون بلا ردّ: {$c->title}",
                'لم يردّ المؤثر على العرض خلال المهلة. تابِع معه أو أعِد الترشيح.',
                "/app/collaborations/{$c->id}", $meta + ['cta_label' => 'عرض التعاون'], $c,
            );
            $sent = true;
        }

        if ($sent) {
            AuditLogger::log('collaboration.response_reminded', $c, ['offered_at' => $c->offered_at?->format('Y-m-d H:i')], $c->tenant_id, null);
        }

        return $sent;
    }
}
