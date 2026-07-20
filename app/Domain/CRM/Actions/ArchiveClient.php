<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Services\UsageMeterService;
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Models\Organization;
use Illuminate\Support\Facades\DB;
class ArchiveClient {
    public function __construct(private UsageMeterService $usage) {}
    public function handle(Organization $org, Client $client): Client {
        return DB::transaction(function () use ($org, $client) {
            $wasCounting = $client->counts();
            $client->update(['status' => 'archived', 'archived_at' => now()]);
            $client->delete(); // soft delete
            if ($wasCounting) {
                $this->usage->release($org, 'customers.max', 1, 'client:archive:' . $client->id); // idempotent
            }
            AuditLogger::log('client.archived', $client, [], $org->tenant_id);
            return $client;
        });
    }
}
