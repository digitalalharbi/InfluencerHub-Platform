<?php
namespace App\Domain\Tenancy\Support;
use Illuminate\Support\Facades\Cache;
/** مفاتيح Cache معزولة حسب المستأجر الحالي. */
class TenantCache {
    public static function key(string $k): string {
        return 'tenant:' . (TenantContext::tenantId() ?? 'none') . ':' . $k;
    }
    public static function put(string $k, $v, $ttl = null) { return Cache::put(self::key($k), $v, $ttl); }
    public static function get(string $k, $default = null) { return Cache::get(self::key($k), $default); }
}
