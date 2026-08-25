<?php

namespace App\Http\Portal;

use App\Domain\Communications\Models\Notification;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * سياق بوّابة العميل — نفس ما يبنيه EnsureClientMember بالضبط (activeClient،
 * clientMembership، myClients، clientUnread)، بلا كتابة جلسة داخل الحلّال نفسه.
 */
final class ClientPortalContextResolver implements PortalContextResolver
{
    public function resolve(User $user, ?int $entityId, bool $exact): ?PortalContext
    {
        [$active, $client, $myClients] = TenantContext::withBypass(function () use ($user, $entityId, $exact) {
            $memberships = ClientMember::where('user_id', $user->id)->where('status', 'active')->get();
            $active = $entityId !== null ? $memberships->firstWhere('client_id', $entityId) : null;
            if ($active === null && ! $exact) {
                $active = $memberships->first();   // الوضع العاديّ يسقط إلى الأوّل
            }
            if ($active === null) {
                return [null, null, null];
            }
            $client = Client::withoutGlobalScopes()->find($active->client_id);
            $myClients = Client::withoutGlobalScopes()->whereIn('id', $memberships->pluck('client_id'))->get();

            return [$active, $client, $myClients];
        });

        if ($active === null || $client === null) {
            return null;
        }

        // عدّاد غير المقروء يُحسب تحت مستأجر العميل — كما في الحارس (لا تجاوز هنا).
        $unread = TenantContext::withTenant(
            (int) $client->tenant_id,
            fn () => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
        );

        return new PortalContext(
            tenantId: (int) $client->tenant_id,
            organizationId: null,
            attributes: ['activeClient' => $client, 'clientMembership' => $active],
            share: ['activeClient' => $client, 'clientMembership' => $active, 'myClients' => $myClients, 'clientUnread' => $unread],
            sessionKey: 'active_client_id',
            sessionValue: (int) $active->client_id,
        );
    }
}
