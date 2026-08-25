<?php

namespace App\Domain\Platform\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * منحة معاينة موقَّعة قصيرة العمر (§P3) — تحمل الرباعية الدقيقة كاملةً وتوقَّع بـHMAC
 * على مفتاح التطبيق، فلا تُزوَّر ولا تُعدَّل. تُحمَل في الـURL (متعدّد النوافذ آمن،
 * بلا حالة جلسة). التحقّق يعيد المطالبات أو null (منتهية/مزوّرة/معدّلة).
 *
 * المطالبات: owner (معرّف مالك المنصّة)، user (الهدف)، tenant، portal، entity،
 * org (إن وُجد)، exp (طابع زمني للانتهاء)، jti (معرّف الجلسة/المنحة للتدقيق).
 */
final class PlatformPreviewToken
{
    private const TTL_SECONDS = 900;   // 15 دقيقة — قصيرة

    /** يُصدر منحة موقَّعة. exp يُمرَّر من الخارج (تفاديًا لاستدعاء الوقت في مواضع مقيَّدة). */
    public static function issue(int $ownerId, int $targetUserId, int $tenantId, string $portal, int $entityId, ?int $organizationId, int $nowTs): string
    {
        $claims = [
            'owner' => $ownerId, 'user' => $targetUserId, 'tenant' => $tenantId,
            'portal' => $portal, 'entity' => $entityId, 'org' => $organizationId,
            'exp' => $nowTs + self::TTL_SECONDS, 'jti' => (string) Str::uuid(),
        ];
        $payload = self::b64(json_encode($claims, JSON_UNESCAPED_UNICODE));
        return $payload . '.' . self::sign($payload);
    }

    /**
     * يتحقّق من التوقيع والانتهاء ويعيد المطالبات، أو null. الوقت الحالي يُمرَّر
     * من الخارج للتحقّق من الانتهاء.
     * @return array{owner:int,user:int,tenant:int,portal:string,entity:int,org:?int,exp:int,jti:string}|null
     */
    public static function verify(?string $token, int $nowTs): ?array
    {
        if (! is_string($token) || ! str_contains($token, '.')) {
            return null;
        }
        [$payload, $sig] = explode('.', $token, 2);
        if (! hash_equals(self::sign($payload), (string) $sig)) {
            return null;   // توقيع غير صحيح ⇒ مزوّر/معدّل
        }
        $claims = json_decode(self::unb64($payload), true);
        if (! is_array($claims) || ! isset($claims['exp'], $claims['owner'], $claims['user'], $claims['tenant'], $claims['portal'], $claims['entity'])) {
            return null;
        }
        if ((int) $claims['exp'] < $nowTs) {
            return null;   // منتهية
        }
        return [
            'owner' => (int) $claims['owner'], 'user' => (int) $claims['user'], 'tenant' => (int) $claims['tenant'],
            'portal' => (string) $claims['portal'], 'entity' => (int) $claims['entity'],
            'org' => isset($claims['org']) ? (int) $claims['org'] : null,
            'exp' => (int) $claims['exp'], 'jti' => (string) ($claims['jti'] ?? ''),
        ];
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', 'pv1:' . $payload, (string) config('app.key'));
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
