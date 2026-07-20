<?php
namespace App\Domain\Billing\Services;
use App\Domain\Billing\Exceptions\EntitlementLimitExceeded;
use App\Domain\Billing\Models\{UsageAggregate, UsageRecord};
use App\Domain\Tenancy\Models\Organization;
use Illuminate\Support\Facades\DB;

/** قياس استهلاك ذرّي، idempotent، معزول بالمستأجر، واعٍ بالدورة. */
class UsageMeterService {
    public function __construct(private EntitlementService $entitlements) {}

    private function period(): array {
        return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
    }

    public function currentUsage(Organization $org, string $feature): int {
        [$ps] = $this->period();
        return (int) UsageAggregate::where('organization_id', $org->id)
            ->where('feature_key', $feature)->where('period_start', $ps)->value('used');
    }

    public function remaining(Organization $org, string $feature): ?int {
        $limit = $this->entitlements->limit($org, $feature);
        if ($limit === null) return null; // unlimited
        return max(0, $limit - $this->currentUsage($org, $feature));
    }

    public function isAllowed(Organization $org, string $feature, int $amount = 1): bool {
        $limit = $this->entitlements->limit($org, $feature);
        if ($limit === null) return true;
        return $this->currentUsage($org, $feature) + $amount <= $limit;
    }

    /** استهلاك ذرّي مع قفل صف التجميع + idempotency. يرمي عند تجاوز الحد. */
    public function consume(Organization $org, string $feature, int $amount = 1, ?string $idempotencyKey = null, ?int $actorUserId = null): void {
        [$ps, $pe] = $this->period();
        DB::transaction(function () use ($org, $feature, $amount, $idempotencyKey, $actorUserId, $ps, $pe) {
            // idempotency: نفس المفتاح لا يُعدّ مرتين
            if ($idempotencyKey) {
                $exists = UsageRecord::where('organization_id', $org->id)->where('feature_key', $feature)
                    ->where('idempotency_key', $idempotencyKey)->exists();
                if ($exists) return;
            }
            // صف تجميع مقفول للتزامن
            $agg = UsageAggregate::where('organization_id', $org->id)->where('feature_key', $feature)
                ->where('period_start', $ps)->lockForUpdate()->first();
            if (! $agg) {
                $agg = UsageAggregate::create(['tenant_id' => $org->tenant_id, 'organization_id' => $org->id, 'feature_key' => $feature, 'period_start' => $ps, 'period_end' => $pe, 'used' => 0]);
                $agg = UsageAggregate::whereKey($agg->id)->lockForUpdate()->first();
            }
            $limit = $this->entitlements->limit($org, $feature);
            if ($limit !== null && $agg->used + $amount > $limit) {
                throw new EntitlementLimitExceeded("تجاوز حد {$feature}: {$agg->used}+{$amount} > {$limit}");
            }
            UsageRecord::create(['tenant_id' => $org->tenant_id, 'organization_id' => $org->id, 'feature_key' => $feature, 'amount' => $amount,
                'period_start' => $ps, 'period_end' => $pe, 'idempotency_key' => $idempotencyKey, 'actor_user_id' => $actorUserId]);
            $agg->increment('used', $amount);
        });
    }

    public function release(Organization $org, string $feature, int $amount = 1, ?string $idempotencyKey = null): void {
        [$ps, $pe] = $this->period();
        DB::transaction(function () use ($org, $feature, $amount, $idempotencyKey, $ps, $pe) {
            if ($idempotencyKey) {
                $exists = UsageRecord::where('organization_id', $org->id)->where('feature_key', $feature)
                    ->where('idempotency_key', $idempotencyKey)->exists();
                if ($exists) return; // release idempotent
            }
            $agg = UsageAggregate::where('organization_id', $org->id)->where('feature_key', $feature)
                ->where('period_start', $ps)->lockForUpdate()->first();
            if (! $agg) return;
            UsageRecord::create(['tenant_id' => $org->tenant_id, 'organization_id' => $org->id, 'feature_key' => $feature, 'amount' => -$amount,
                'period_start' => $ps, 'period_end' => $pe, 'idempotency_key' => $idempotencyKey]);
            $agg->update(['used' => max(0, $agg->used - $amount)]);
        });
    }

    /** إعادة حساب التجميع من السجلات (مصدر الحقيقة). */
    public function recalculate(Organization $org, string $feature): int {
        [$ps] = $this->period();
        $sum = (int) UsageRecord::where('organization_id', $org->id)->where('feature_key', $feature)
            ->where('period_start', $ps)->sum('amount');
        UsageAggregate::where('organization_id', $org->id)->where('feature_key', $feature)
            ->where('period_start', $ps)->update(['used' => max(0, $sum)]);
        return max(0, $sum);
    }
}
