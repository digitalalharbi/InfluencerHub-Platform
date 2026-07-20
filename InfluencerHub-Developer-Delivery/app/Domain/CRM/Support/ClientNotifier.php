<?php

namespace App\Domain\CRM\Support;

use App\Domain\Communications\Services\NotificationService;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

/** يوجّه إشعارات بوابة العميل لأعضائه النشِطين (اختياريًا بأدوار محدّدة). */
class ClientNotifier
{
    public function __construct(private NotificationService $notifications) {}

    /** يُشعر كل أعضاء العميل النشِطين (أو المطابقين للأدوار) بحدث. */
    public function toClientMembers(Client $client, string $type, string $category, string $title, ?string $body = null, ?string $actionUrl = null, array $data = [], ?Model $subject = null, ?array $roles = null): void
    {
        $userIds = $this->activeMemberIds($client, $roles);
        if ($userIds) {
            $this->notifications->notifyMany($client->tenant_id, $userIds, $type, $category, $title, $body, $actionUrl, $data, $subject);
        }
    }

    /** يُشعر مستخدمًا واحدًا (مثل مُرسِل طلب التعديل). */
    public function toUser(int $tenantId, int $userId, string $type, string $category, string $title, ?string $body = null, ?string $actionUrl = null, array $data = [], ?Model $subject = null): void
    {
        $this->notifications->notify($tenantId, $userId, $type, $category, $title, $body, $actionUrl, $data, $subject);
    }

    private function activeMemberIds(Client $client, ?array $roles): array
    {
        return TenantContext::withTenant($client->tenant_id, function () use ($client, $roles) {
            $q = ClientMember::where('client_id', $client->id)->where('status', 'active');
            if ($roles) $q->whereIn('role', $roles);

            return $q->pluck('user_id')->all();
        });
    }
}
