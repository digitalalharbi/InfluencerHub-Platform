<?php

namespace App\Domain\Nomination\Access;

use App\Domain\Nomination\Models\FeatureAvailability;

/**
 * مُحلِّل إتاحة الميزات المُدارة من المنصّة — المصدر الوحيد لقرار «هل الميزة متاحة لنطاق؟».
 *
 * غياب أي صفّ = مُتاحة افتراضيًّا (لا تنكسر ميزة قائمة). الأخصّ يفوز:
 * tenant+workspace+portal ← tenant+workspace ← tenant+portal ← tenant ←
 * global+portal ← global ← الافتراضي (مُتاحة).
 */
final class FeatureAvailabilityResolver
{
    /** هل الميزة مُتاحة للنطاق المُعطى؟ (افتراض مُتاحة عند غياب أي صفّ صريح). */
    public function enabled(string $feature, ?int $tenantId, ?int $workspaceId = null, ?string $portal = null): bool
    {
        $rows = FeatureAvailability::query()
            ->where('feature_key', $feature)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $workspaceId))
            ->where(fn ($q) => $q->whereNull('portal')->orWhere('portal', $portal))
            ->get();

        if ($rows->isEmpty()) {
            return true; // لا قرار صريح ⇒ مُتاحة
        }

        // اختر الأخصّ: أوزان الأبعاد (مستأجر > مساحة > بوّابة) تضمن ترتيبًا حتميًّا.
        $best = null;
        $bestScore = -1;
        foreach ($rows as $row) {
            $score = ($row->tenant_id !== null ? 4 : 0)
                + ($row->workspace_id !== null ? 2 : 0)
                + ($row->portal !== null ? 1 : 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        return (bool) $best->enabled;
    }

    /** يضبط إتاحة صريحة لنطاق (upsert بمطابقة null دقيقة). تديره المنصّة فقط. */
    public function set(string $feature, ?int $tenantId, ?int $workspaceId, ?string $portal, bool $enabled, ?int $setBy = null, ?string $reason = null): FeatureAvailability
    {
        $existing = FeatureAvailability::query()
            ->where('feature_key', $feature)
            ->where(fn ($q) => $tenantId === null ? $q->whereNull('tenant_id') : $q->where('tenant_id', $tenantId))
            ->where(fn ($q) => $workspaceId === null ? $q->whereNull('workspace_id') : $q->where('workspace_id', $workspaceId))
            ->where(fn ($q) => $portal === null ? $q->whereNull('portal') : $q->where('portal', $portal))
            ->first();

        if ($existing) {
            $existing->update(['enabled' => $enabled, 'set_by' => $setBy, 'reason' => $reason]);

            return $existing;
        }

        return FeatureAvailability::create([
            'feature_key' => $feature,
            'tenant_id' => $tenantId,
            'workspace_id' => $workspaceId,
            'portal' => $portal,
            'enabled' => $enabled,
            'set_by' => $setBy,
            'reason' => $reason,
        ]);
    }
}
