<?php
namespace App\Domain\Creators\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Creators\Enums\CreatorType;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class CreateCreator {
    public function handle(Organization $org, array $data, User $actor): Creator {
        // المدخل المفضّل قدرات؛ النوع القديم يُقبل ممّن لم يُهاجَر بعد (أوامر البذر).
        $caps = CreatorCapabilityService::normalize($data['capabilities'] ?? []);
        if (! $caps) {
            if (! in_array($data['type'] ?? '', CreatorType::values(), true)) {
                throw new RuntimeException('اختر قدرة واحدة على الأقل للمبدع.');
            }
            $caps = CreatorCapabilityService::LEGACY_TO_CAPS[$data['type']];
        }
        unset($data['capabilities']); // ليست عمودًا في `creators` — تُكتب كصفوف

        return DB::transaction(function () use ($org, $data, $actor, $caps) {
            $seq = Creator::withTrashed()->where('tenant_id', $org->tenant_id)->count() + 1;
            $creator = Creator::create(array_merge($data, [
                'tenant_id' => $org->tenant_id,
                'creator_number' => 'CR-' . $org->tenant_id . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'type' => CreatorCapabilityService::legacyType($caps), // كتابة مزدوجة (انظر sync)
                'status' => $data['status'] ?? 'prospect',
                'created_by' => $actor->id,
            ]));
            CreatorCapabilityService::sync($creator, $caps);
            AuditLogger::log('creator.created', $creator, ['capabilities' => $caps, 'type' => $creator->type], $org->tenant_id, $actor->id);
            return $creator;
        });
    }
}
