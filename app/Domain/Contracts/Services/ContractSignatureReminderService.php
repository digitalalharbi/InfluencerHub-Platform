<?php

namespace App\Domain\Contracts\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * تذكير توقيع الطرف على العقد المُرسَل — تذكير حقيقيّ مبنيّ على sent_at الفعليّة.
 *
 * إن بقي العقد «sent» (مُرسَل بلا توقيع) بعد المهلة، يُذكَّر الطرف (المبدع أو أعضاء
 * العميل، بحسب party_type) وصاحب العقد (الوكالة) مرّة واحدة، وتُحفظ pending_reminded_at
 * لمنع التكرار. يعمل مجدولًا على غرار تذكيرات SLA/الفاتورة/النشر/ردّ المؤثر/قرار العميل.
 *
 * القبول داخل المنصّة تسجيلُ موافقةٍ لا توقيعٌ قانونيّ خارجيّ — والتذكير هنا متابعةٌ
 * إداريّة صادقة وآمنة من التكرار فقط. المهلة الافتراضيّة ٧٢ ساعة (قابلة للضبط).
 */
final class ContractSignatureReminderService
{
    /** مهلة انتظار توقيع الطرف قبل التذكير (ساعات). */
    public function __construct(private NotificationService $notifications, private int $signatureWindowHours = 72) {}

    /** يمسح كل المستأجرين ويُذكّر بالعقود المُرسَلة المعلّقة على التوقيع. */
    public function scan(): array
    {
        return TenantContext::withBypass(function () {
            $cutoff = now()->subHours($this->signatureWindowHours);

            $pending = Contract::query()
                ->where('status', 'sent')                   // مُرسَل، بلا توقيع بعد
                ->whereNotNull('sent_at')
                ->whereNull('signed_at')
                ->whereNull('pending_reminded_at')          // لم يُذكَّر بعد (منع التكرار)
                ->where('sent_at', '<=', $cutoff)           // مضت مهلة التوقيع
                ->get();

            $notified = 0;
            foreach ($pending as $c) {
                $c->forceFill(['pending_reminded_at' => now()])->saveQuietly(); // العلامة أوّلًا: إعادة التشغيل آمنة
                if ($this->remind($c)) {
                    $notified++;
                }
            }

            return ['scanned' => $pending->count(), 'notified' => $notified];
        });
    }

    /** يُذكّر الطرف (بوابته) وصاحب العقد (الوكالة). يُرجع true إن أُرسل لأحد. */
    private function remind(Contract $c): bool
    {
        $sent = false;
        $meta = ['objects' => [['type' => 'contract', 'name' => $c->title]], 'priority' => 'high', 'contract_id' => $c->id];
        $partyTitle = "عقد بانتظار موافقتك: {$c->title}";
        $partyBody = 'أرسلنا إليك العقد ولم يصلنا قبولك بعد. راجِعه وسجّل موافقتك حتى يمضي التعاون.';

        if ($c->party_type === 'creator' && $c->creator_id && ($uid = Creator::find($c->creator_id)?->user_id)) {
            $this->notifications->notify($c->tenant_id, (int) $uid, 'contract.signature_reminder', 'creators', $partyTitle, $partyBody, '/creator/contracts', $meta + ['cta_label' => 'عرض العقد'], $c);
            $sent = true;
        }

        if ($c->party_type === 'client' && $c->client_id) {
            $members = ClientMember::where('client_id', $c->client_id)->where('status', 'active')->pluck('user_id')->all();
            foreach ($members as $mid) {
                $this->notifications->notify($c->tenant_id, (int) $mid, 'contract.signature_reminder', 'campaigns', $partyTitle, $partyBody, '/client/contracts', $meta + ['cta_label' => 'عرض العقد'], $c);
                $sent = true;
            }
        }

        if ($c->created_by) {
            $this->notifications->notify(
                $c->tenant_id, (int) $c->created_by, 'contract.signature_reminder', 'campaigns',
                "عقد بلا توقيع: {$c->title}",
                'لم يوقّع الطرف على العقد خلال المهلة. تابِع معه لإتمام التعاون.',
                "/app/contracts/{$c->id}", $meta + ['cta_label' => 'عرض العقد'], $c,
            );
            $sent = true;
        }

        if ($sent) {
            AuditLogger::log('contract.signature_reminded', $c, ['sent_at' => $c->sent_at?->format('Y-m-d H:i')], $c->tenant_id, null);
        }

        return $sent;
    }
}
