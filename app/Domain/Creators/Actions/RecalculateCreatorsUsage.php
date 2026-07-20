<?php
namespace App\Domain\Creators\Actions;
use App\Domain\Billing\Models\UsageAggregate;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Models\Organization;
class RecalculateCreatorsUsage {
    /** يعيد حساب استهلاك creators.max من جدول creators الفعلي (يصحّح الانحراف). */
    public function handle(Organization $org): int {
        $count = Creator::where('tenant_id', $org->tenant_id)->whereNull('deleted_at')->count();
        $ps = now()->startOfMonth()->toDateString();
        UsageAggregate::updateOrCreate(
            ['organization_id' => $org->id, 'feature_key' => 'creators.max', 'period_start' => $ps],
            ['tenant_id' => $org->tenant_id, 'period_end' => now()->endOfMonth()->toDateString(), 'used' => $count]
        );
        return $count;
    }
}
