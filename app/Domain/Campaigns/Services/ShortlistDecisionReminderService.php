<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Campaigns\Models\CampaignShortlistVersion;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * تذكير قرار العميل على الترشيح — تذكير حقيقيّ مبنيّ على submitted_at الفعليّة.
 *
 * إن بقي إصدار الترشيح «submitted» (مُرسَل للعميل بلا قرار) بعد المهلة، يُذكَّر أعضاء
 * العميل (بوابتهم) وصاحب الحملة (الوكالة) مرّة واحدة، وتُحفظ client_decision_reminded_at
 * لمنع التكرار. يعمل مجدولًا على غرار تذكيرات SLA/الفاتورة/النشر/ردّ المؤثر.
 *
 * لا يخترع تصعيدًا: تذكير أوّليّ صادق وآمن من التكرار فقط. المهلة الافتراضيّة ٧٢ ساعة
 * (قابلة للضبط) — نافذة قرار معقولة لا تُغرِق العميل ولا تترك الترشيح معلّقًا بلا متابعة.
 */
final class ShortlistDecisionReminderService
{
    /** مهلة انتظار قرار العميل قبل التذكير (ساعات). */
    public function __construct(private NotificationService $notifications, private int $decisionWindowHours = 72) {}

    /** يمسح كل المستأجرين ويُذكّر بإصدارات الترشيح المعلّقة على قرار العميل. */
    public function scan(): array
    {
        return TenantContext::withBypass(function () {
            $cutoff = now()->subHours($this->decisionWindowHours);

            $pending = CampaignShortlistVersion::query()
                ->where('status', 'submitted')                  // مُرسَل للعميل، لا قرار بعد
                ->whereNotNull('submitted_at')
                ->whereNull('decided_at')
                ->whereNull('client_decision_reminded_at')      // لم يُذكَّر بعد (منع التكرار)
                ->where('submitted_at', '<=', $cutoff)          // مضت مهلة القرار
                ->with('shortlist.campaign')
                ->get();

            $notified = 0;
            foreach ($pending as $v) {
                $v->forceFill(['client_decision_reminded_at' => now()])->saveQuietly(); // العلامة أوّلًا: إعادة التشغيل آمنة
                if ($this->remind($v)) {
                    $notified++;
                }
            }

            return ['scanned' => $pending->count(), 'notified' => $notified];
        });
    }

    /** يُذكّر أعضاء العميل (بوابتهم) وصاحب الحملة (الوكالة). يُرجع true إن أُرسل لأحد. */
    private function remind(CampaignShortlistVersion $v): bool
    {
        $campaign = $v->shortlist?->campaign;
        if (! $campaign) {
            return false;
        }

        $sent = false;
        $meta = ['objects' => [['type' => 'campaign', 'name' => $campaign->name]], 'priority' => 'high', 'campaign_id' => $campaign->id];

        if ($campaign->client_id) {
            $members = ClientMember::where('client_id', $campaign->client_id)->where('status', 'active')->pluck('user_id')->all();
            foreach ($members as $uid) {
                $this->notifications->notify(
                    $v->tenant_id, (int) $uid, 'shortlist.decision_reminder', 'campaigns',
                    "ترشيح بانتظار قرارك: {$campaign->name}",
                    'أرسلنا إليك قائمة ترشيح المؤثرين ولم يصلنا قرارك بعد. اعتمد من يناسبك حتى تمضي الحملة.',
                    "/client/campaigns/{$campaign->id}/shortlist", $meta + ['cta_label' => 'مراجعة الترشيح'], $v,
                );
                $sent = true;
            }
        }

        if ($campaign->created_by) {
            $this->notifications->notify(
                $v->tenant_id, (int) $campaign->created_by, 'shortlist.decision_reminder', 'campaigns',
                "ترشيح بلا قرار من العميل: {$campaign->name}",
                'لم يصل قرار العميل على الترشيح خلال المهلة. تابِع معه لتحريك الحملة.',
                "/app/campaigns/{$campaign->id}/shortlist", $meta + ['cta_label' => 'عرض الترشيح'], $v,
            );
            $sent = true;
        }

        if ($sent) {
            AuditLogger::log('shortlist.decision_reminded', $v, ['submitted_at' => $v->submitted_at?->format('Y-m-d H:i')], $v->tenant_id, null);
        }

        return $sent;
    }
}
