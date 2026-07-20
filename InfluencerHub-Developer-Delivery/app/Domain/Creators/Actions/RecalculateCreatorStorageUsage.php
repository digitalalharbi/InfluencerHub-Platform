<?php
namespace App\Domain\Creators\Actions;
use App\Domain\Billing\Models\UsageAggregate;
use App\Domain\Creators\Services\ApplicationDocumentService;
use App\Domain\Tenancy\Models\Organization;
class RecalculateCreatorStorageUsage {
    public function __construct(private ApplicationDocumentService $docs) {}
    /** يعيد حساب استهلاك التخزين (GB) من الملفات الفعلية. */
    public function handle(Organization $org): int {
        $bytes = $this->docs->tenantStorageBytes($org->tenant_id);
        $gb = (int) ceil($bytes / (1024 * 1024 * 1024));
        $ps = now()->startOfMonth()->toDateString();
        UsageAggregate::updateOrCreate(
            ['organization_id' => $org->id, 'feature_key' => 'creator_storage.gb', 'period_start' => $ps],
            ['tenant_id' => $org->tenant_id, 'period_end' => now()->endOfMonth()->toDateString(), 'used' => $gb]
        );
        return $bytes;
    }
}
