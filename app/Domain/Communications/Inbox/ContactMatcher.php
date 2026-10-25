<?php

namespace App\Domain\Communications\Inbox;

use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * مطابقة جهة اتّصال (هاتف/بريد) بمبدع أو عميل ضمن مستأجر (COMMS-01). لا يدّعي مطابقة بلا
 * تطابق فعليّ — رقم/بريد لا يقابل سجلًّا ⇒ null (الحدث يبقى بلا ربط، لا نلفّقه). الهاتف يُطبَّع
 * إلى أرقام فقط قبل المقارنة كي يتطابق رغم اختلاف التنسيق (+، مسافات، شُرَط).
 */
class ContactMatcher
{
    /** @return array{creator_id:?int,client_id:?int} */
    public function byPhone(int $tenantId, string $phone): array
    {
        $digits = self::digits($phone);
        if ($digits === '') {
            return ['creator_id' => null, 'client_id' => null];
        }

        return TenantContext::withTenant($tenantId, function () use ($tenantId, $digits) {
            $creator = Creator::where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->whereRaw(self::digitsSql('phone').' = ?', [$digits])
                    ->orWhereRaw(self::digitsSql('whatsapp').' = ?', [$digits]))
                ->first();
            $client = Client::where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->whereRaw(self::digitsSql('phone').' = ?', [$digits])
                    ->orWhereRaw(self::digitsSql('whatsapp').' = ?', [$digits]))
                ->first();

            return ['creator_id' => $creator?->id, 'client_id' => $client?->id];
        });
    }

    /** @return array{creator_id:?int,client_id:?int} */
    public function byEmail(int $tenantId, string $email): array
    {
        $e = mb_strtolower(trim($email));
        if ($e === '') {
            return ['creator_id' => null, 'client_id' => null];
        }

        return TenantContext::withTenant($tenantId, function () use ($tenantId, $e) {
            $creator = Creator::where('tenant_id', $tenantId)->whereRaw('lower(email) = ?', [$e])->first();
            $client = Client::where('tenant_id', $tenantId)->whereRaw('lower(email) = ?', [$e])->first();

            return ['creator_id' => $creator?->id, 'client_id' => $client?->id];
        });
    }

    /**
     * يحلّ المستأجر المالك لرقم (الويبهوك بلا سياق مستأجر). يبحث عبر المستأجرين بتجاوز مؤقّت،
     * ويُعيد أوّل مبدع/عميل مطابق مع مستأجره، أو null-tenant إن لم يُطابِق أحد (لا يُلفَّق مستأجر).
     *
     * @return array{tenant_id:?int,creator_id:?int,client_id:?int}
     */
    public function resolveAcrossTenants(string $phone): array
    {
        $digits = self::digits($phone);
        if ($digits === '') {
            return ['tenant_id' => null, 'creator_id' => null, 'client_id' => null];
        }

        return TenantContext::withBypass(function () use ($digits) {
            $creator = Creator::whereRaw(self::digitsSql('phone').' = ?', [$digits])
                ->orWhereRaw(self::digitsSql('whatsapp').' = ?', [$digits])->first();
            if ($creator) {
                return ['tenant_id' => (int) $creator->tenant_id, 'creator_id' => $creator->id, 'client_id' => null];
            }
            $client = Client::whereRaw(self::digitsSql('phone').' = ?', [$digits])
                ->orWhereRaw(self::digitsSql('whatsapp').' = ?', [$digits])->first();
            if ($client) {
                return ['tenant_id' => (int) $client->tenant_id, 'creator_id' => null, 'client_id' => $client->id];
            }

            return ['tenant_id' => null, 'creator_id' => null, 'client_id' => null];
        });
    }

    public static function digits(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    /** SQL يجرّد غير الأرقام من عمود (PostgreSQL) للمقارنة بتنسيق مُطبَّع. */
    private static function digitsSql(string $column): string
    {
        return "regexp_replace(coalesce({$column},''), '\\D', '', 'g')";
    }
}
