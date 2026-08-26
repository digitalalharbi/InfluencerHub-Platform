<?php

namespace App\Domain\Nomination\Support;

use App\Domain\Campaigns\Models\CampaignShortlistItem;

/**
 * الإسقاط القانونيّ الآمن لبند ترشيح كما يراه العميل — مصدر واحد بدل الإسقاطات المضمّنة
 * المتناثرة (كانت خطر تسريب: كلٌّ يُعيد اشتقاق قائمة الحقول الآمنة يدويًّا).
 *
 * العميل يرى: اسم المبدع، الحساب، المنصّة، حجم الجمهور، السعر المخصّص له (بيع)، سبب الترشيح،
 * وقراره. ولا يرى أبدًا: تكلفة المبدع، الهامش، مستحقّات المبدع، مصدرًا أو ملاحظات داخليّة،
 * أو بيانات بنكيّة. (تحصيل العميل ليس مستحقّات المبدع.)
 */
final class ClientNominationView
{
    /**
     * @return array<string,mixed>
     */
    public static function item(CampaignShortlistItem $it): array
    {
        $decision = $it->client_decision ?? 'pending';

        return [
            'id' => $it->id,
            'creator' => $it->creator?->display_name ?? '—',
            'handle' => $it->creator?->handle,
            'platform' => $it->creator?->primary_platform,
            'followers' => (int) ($it->creator?->followers_count ?? 0),
            'isBackup' => (bool) $it->is_backup,
            // السعر المخصّص للعميل (proposed_fee_minor) = سعر البيع فقط — لا تكلفة ولا هامش.
            'feeMinor' => (int) $it->proposed_fee_minor,
            // نسبة الملاءمة وسببها — إشارات وصفيّة غير ماليّة (شفافيّة للعميل، لا تسريب).
            'score' => (int) $it->match_score,
            'reasons' => array_values($it->reasons ?? []),
            'decision' => $decision,
            'decisionLabel' => ['approved' => 'اعتمدته', 'rejected' => 'رفضته', 'needs_alternative' => 'طلبت بديلًا'][$decision] ?? 'بانتظار قرارك',
            'decisionTone' => ['approved' => 'approved', 'rejected' => 'rejected', 'needs_alternative' => 'under_review'][$decision] ?? 'draft',
        ];
    }

    /**
     * إسقاط قائمة بنود لإصدار — مصدر واحد لبوّابة العميل.
     *
     * @param  iterable<CampaignShortlistItem>  $items
     * @return array<int,array<string,mixed>>
     */
    public static function items(iterable $items): array
    {
        $out = [];
        foreach ($items as $it) {
            $out[] = self::item($it);
        }

        return $out;
    }
}
