<?php

namespace App\Domain\Publishers\Support;

use App\Support\Platforms\PlatformRegistry;

/**
 * موصّلات ذكاء الناشرين — تُبنى فوق PlatformRegistry. تصرّح لكل منصّة:
 * الحالة، القدرات، مصادر البيانات، والصلاحية — بصدق (لا اتصال حيّ وهمي).
 *
 * القاعدة الحالية: كل المنصّات available_manual (لا API اكتشاف حيّ) → حالة الاكتشاف الحيّ
 * = waiting_for_credentials؛ الإدخال اليدوي والاستيراد متاحان الآن.
 */
class PublisherConnectors
{
    /** الحالات المعتمدة (لغة صادقة). */
    public const STATES = [
        'connected' => 'مُتّصل', 'manual' => 'يدوي', 'import' => 'استيراد', 'sandbox' => 'تجريبي',
        'waiting_for_credentials' => 'بانتظار الاعتماد', 'waiting_for_approval' => 'بانتظار الموافقة',
        'limited' => 'محدود', 'degraded' => 'متدهور', 'unavailable' => 'غير متاح',
    ];

    public const STATE_TONE = [
        'connected' => 'approved', 'manual' => 'submitted', 'import' => 'submitted', 'sandbox' => 'under_review',
        'waiting_for_credentials' => 'draft', 'waiting_for_approval' => 'draft',
        'limited' => 'under_review', 'degraded' => 'rejected', 'unavailable' => 'archived',
    ];

    /** @return array<int,array<string,mixed>> موصّل لكل منصّة متاحة في السجل. */
    public static function all(): array
    {
        $out = [];
        foreach (PlatformRegistry::all() as $key => $p) {
            $status = $p['status'] ?? 'draft';
            if ($status === 'draft') continue; // غير متاح بعد — لا يُعرض

            // الاكتشاف الحيّ (live discovery) غير مُفعّل → بانتظار الاعتماد؛ اليدوي/الاستيراد متاحان.
            $discoveryState = 'waiting_for_credentials';
            $caps = $p['capabilities'] ?? [];
            $dataSources = ['manual', 'import'];

            $out[] = [
                'key' => $key,
                'name' => $p['label_ar'] ?? $key,
                'nameEn' => $p['label_en'] ?? $key,
                'discoveryState' => $discoveryState,
                'discoveryLabel' => self::STATES[$discoveryState],
                'discoveryTone' => self::STATE_TONE[$discoveryState],
                'manualAvailable' => true,
                'capabilities' => array_values(array_intersect($caps, ['audience_data', 'creator_profile', 'content_publishing', 'publishing_verification'])),
                'dataSources' => $dataSources,
                'lastSyncedAt' => null,     // لا مزامنة حيّة بعد
                'refreshRate' => null,      // يُحدَّد عند تفعيل الـAPI
                'note' => 'الاكتشاف الحيّ عبر API غير مُفعّل — الإدخال يدوي/استيراد. تُرفَّع الحالة تلقائيًا عند توفّر الاعتماد.',
            ];
        }
        return $out;
    }

    /** @return array{total:int,manual:int,live:int} ملخّص صادق. */
    public static function summary(): array
    {
        $all = self::all();
        return [
            'total' => count($all),
            'manual' => count(array_filter($all, fn ($c) => $c['manualAvailable'])),
            'live' => count(array_filter($all, fn ($c) => $c['discoveryState'] === 'connected')),
        ];
    }
}
