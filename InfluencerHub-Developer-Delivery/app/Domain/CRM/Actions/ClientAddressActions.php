<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\{Client, ClientAddress};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** عمليات عناوين العميل. client_id يُؤخذ من العميل النشِط (لا من النموذج). */
class ClientAddressActions {
    public function create(Client $client, array $data, int $actorId): ClientAddress {
        return DB::transaction(fn () => TenantContext::withTenant($client->tenant_id, function () use ($client, $data, $actorId) {
            $addr = ClientAddress::create($data + ['tenant_id' => $client->tenant_id, 'client_id' => $client->id, 'created_by' => $actorId]);
            if (! empty($data['is_default'])) { $this->makeDefault($addr); }
            AuditLogger::log('client_address.created', $addr, ['type' => $addr->type], $client->tenant_id, $actorId);
            return $addr;
        }));
    }
    public function update(ClientAddress $addr, array $data, int $actorId): ClientAddress {
        return TenantContext::withTenant($addr->tenant_id, function () use ($addr, $data, $actorId) {
            $addr->update($data + ['updated_by' => $actorId]);
            AuditLogger::log('client_address.updated', $addr, array_keys($data), $addr->tenant_id, $actorId);
            return $addr->fresh();
        });
    }
    public function setDefault(ClientAddress $addr, int $actorId): void {
        if ($addr->archived_at) throw new RuntimeException('لا يمكن جعل عنوان مؤرشف افتراضيًا.');
        DB::transaction(fn () => TenantContext::withTenant($addr->tenant_id, function () use ($addr, $actorId) {
            $this->makeDefault($addr);
            AuditLogger::log('client_address.set_default', $addr, [], $addr->tenant_id, $actorId);
        }));
    }
    public function archive(ClientAddress $addr, int $actorId): void {
        TenantContext::withTenant($addr->tenant_id, function () use ($addr, $actorId) {
            $addr->update(['archived_at' => now(), 'is_default' => false]); // المؤرشف لا يبقى افتراضيًا
            AuditLogger::log('client_address.archived', $addr, [], $addr->tenant_id, $actorId);
        });
    }
    public function restore(ClientAddress $addr, int $actorId): void {
        TenantContext::withTenant($addr->tenant_id, function () use ($addr, $actorId) {
            $addr->update(['archived_at' => null]);
            AuditLogger::log('client_address.restored', $addr, [], $addr->tenant_id, $actorId);
        });
    }
    /** عنوان افتراضي واحد فقط لكل نوع (داخل معاملة). */
    private function makeDefault(ClientAddress $addr): void {
        ClientAddress::where('client_id', $addr->client_id)->where('type', $addr->type)->where('id', '!=', $addr->id)->update(['is_default' => false]);
        $addr->update(['is_default' => true]);
    }
}
