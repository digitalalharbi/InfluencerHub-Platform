<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Services\UsageMeterService;
use App\Domain\CRM\Enums\ClientStatus;
use App\Domain\CRM\Models\{Client, ClientStatusHistory};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateClient {
    public function __construct(private UsageMeterService $usage) {}

    /** ينشئ عميلًا؛ يستهلك customers.max (إن كانت الحالة محسوبة) داخل معاملة واحدة. */
    public function handle(Organization $org, array $data, User $actor): Client {
        return DB::transaction(function () use ($org, $data, $actor) {
            $status = $data['status'] ?? 'lead';
            $counts = ClientStatus::from($status)->counts();
            if ($counts) {
                // يرمي EntitlementLimitExceeded عند التجاوز → تُلغى المعاملة (لا استهلاك)
                $this->usage->consume($org, 'customers.max', 1, 'client:create:' . Str::uuid(), $actor->id);
            }
            $seq = Client::withTrashed()->where('tenant_id', $org->tenant_id)->count() + 1;
            $client = Client::create(array_merge($data, [
                'tenant_id' => $org->tenant_id,
                'client_number' => 'CL-' . $org->tenant_id . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'status' => $status,
                'created_by' => $actor->id,
            ]));
            ClientStatusHistory::create(['tenant_id' => $org->tenant_id, 'client_id' => $client->id, 'from_status' => null, 'to_status' => $status, 'changed_by' => $actor->id, 'created_at' => now()]);
            AuditLogger::log('client.created', $client, ['status' => $status], $org->tenant_id, $actor->id);
            return $client;
        });
    }
}
