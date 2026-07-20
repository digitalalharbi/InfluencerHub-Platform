<?php
namespace App\Domain\Audit\Services;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Auth;
class AuditLogger {
    /**
     * يسجّل حدثًا في سجل التدقيق (append-only). $changes يبقى للتوافق؛
     * ويمكن تمرير old/new صراحةً عبر $context['old'|'new'].
     */
    public static function log(
        string $action,
        ?object $auditable = null,
        array $changes = [],
        ?int $tenantId = null,
        ?int $userId = null,
        array $context = [],
    ): AuditLog {
        try {
            $req = request();
            return AuditLog::create([
                'tenant_id' => $tenantId ?? TenantContext::tenantId(),
                'user_id' => $userId ?? Auth::id(),
                'actor_name' => Auth::user()->name ?? 'system',
                'action' => $action,
                'auditable_type' => $auditable ? $auditable::class : null,
                'auditable_id' => $auditable?->getKey(),
                'changes' => $changes ?: null,
                'old_values' => $context['old'] ?? null,
                'new_values' => $context['new'] ?? null,
                'ip' => $req?->ip(),
                'user_agent' => $req?->userAgent(),
                'request_id' => $req?->headers->get('X-Request-Id'),
                'occurred_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // التدقيق لا يُفشل العملية الأساسية
            return new AuditLog();
        }
    }
}
