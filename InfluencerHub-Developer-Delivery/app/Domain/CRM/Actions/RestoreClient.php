<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Services\UsageMeterService;
use App\Domain\CRM\Enums\ClientStatus;
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Models\Organization;
use Illuminate\Support\Facades\DB;
class RestoreClient {
    public function __construct(private UsageMeterService $usage) {}
    public function handle(Organization $org, Client $client, string $toStatus = 'active'): Client {
        return DB::transaction(function () use ($org, $client, $toStatus) {
            if (ClientStatus::from($toStatus)->counts()) {
                $this->usage->consume($org, 'customers.max', 1, 'client:restore:' . $client->id, null); // يرمي عند التجاوز
            }
            $client->restore();
            $client->update(['status' => $toStatus, 'archived_at' => null]);
            AuditLogger::log('client.restored', $client, ['status' => $toStatus], $org->tenant_id);
            return $client;
        });
    }
}
