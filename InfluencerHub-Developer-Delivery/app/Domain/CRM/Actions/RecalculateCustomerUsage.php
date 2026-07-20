<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Billing\Models\UsageAggregate;
use App\Domain\CRM\Enums\ClientStatus;
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Models\Organization;
class RecalculateCustomerUsage {
    /** يحسب العدد الحقيقي للعملاء المحسوبين ويصحّح usage_aggregate. */
    public function handle(Organization $org): int {
        $count = Client::where('tenant_id', $org->tenant_id)
            ->whereIn('status', ClientStatus::countingValues())
            ->whereNull('archived_at')->count();
        $ps = now()->startOfMonth()->toDateString();
        UsageAggregate::updateOrCreate(
            ['organization_id' => $org->id, 'feature_key' => 'customers.max', 'period_start' => $ps],
            ['tenant_id' => $org->tenant_id, 'period_end' => now()->endOfMonth()->toDateString(), 'used' => $count]
        );
        return $count;
    }
}
