<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;
class CreateBrand {
    public function handle(Client $client, array $data, User $actor): Brand {
        $brand = Brand::create(array_merge($data, [
            'tenant_id' => $client->tenant_id, 'client_id' => $client->id,
            'slug' => ($data['slug'] ?? Str::slug($data['name'])) . '-' . Str::lower(Str::random(4)),
            'created_by' => $actor->id,
        ]));
        AuditLogger::log('brand.created', $brand, [], $client->tenant_id, $actor->id);
        return $brand;
    }
}
