<?php

namespace App\Domain\Publishers\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Identity\Models\User;
use App\Domain\Publishers\Models\Publisher;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * تحويل ناشر إلى مؤثر داخل CRM — idempotent: إن كان محوَّلًا مسبقًا يُعيد نفس المؤثر (لا تكرار).
 * ينسخ الحقول المتاحة ويربط publisher_id على المؤثر و converted_creator_id على الناشر.
 */
class ConvertPublisherToInfluencer
{
    /** @param array<int,string>|string $capabilities قدرات، أو نوع قديم نصًّا (توافق خلفي). */
    public function handle(Publisher $publisher, User $actor, array|string $capabilities = ['influencer']): Creator
    {
        $caps = is_string($capabilities)
            ? (CreatorCapabilityService::LEGACY_TO_CAPS[$capabilities] ?? ['influencer'])
            : (CreatorCapabilityService::normalize($capabilities) ?: ['influencer']);

        return DB::transaction(function () use ($publisher, $actor, $caps) {
            return TenantContext::withTenant($publisher->tenant_id, function () use ($publisher, $actor, $caps) {
            // idempotent: موجود مسبقًا
            if ($publisher->converted_creator_id) {
                $existing = Creator::withTrashed()->find($publisher->converted_creator_id);
                if ($existing) { return $existing; }
            }
            // منع تكرار عبر publisher_id
            $linked = Creator::where('publisher_id', $publisher->id)->first();
            if ($linked) {
                $publisher->update(['converted_creator_id' => $linked->id]);

                return $linked;
            }

            $seq = Creator::withTrashed()->where('tenant_id', $publisher->tenant_id)->count() + 1;
            $creator = Creator::create([
                'tenant_id' => $publisher->tenant_id,
                'creator_number' => 'CR-' . $publisher->tenant_id . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'type' => CreatorCapabilityService::legacyType($caps), // كتابة مزدوجة (انظر sync)
                'display_name' => $publisher->display_name ?: $publisher->handle,
                'handle' => ltrim((string) $publisher->handle, '@'),
                'primary_platform' => $publisher->platform,
                'followers_count' => (int) $publisher->followers_count,
                'content_categories' => $publisher->categories ?? [],
                'city' => $publisher->city,
                'status' => 'prospect',
                'publisher_id' => $publisher->id,
                'created_by' => $actor->id,
            ]);
            CreatorCapabilityService::sync($creator, $caps, 'publisher_import');
            $publisher->update(['converted_creator_id' => $creator->id]);
            AuditLogger::log('publisher.converted', $publisher, ['creator_id' => $creator->id], $publisher->tenant_id, $actor->id);

            return $creator;
            });
        });
    }
}
