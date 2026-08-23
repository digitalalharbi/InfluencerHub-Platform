<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Identity\Models\User;
use App\Domain\Ops\Services\SystemHealthService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مركز صحّة النظام (React/Inertia) — للإداريين. حالات حقيقية من فحوص فعلية،
 * بلا أسرار. البنية التحتية العامة (طابور/مجدول/بريد) + تكاملات المستأجر.
 */
class SystemHealthController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'agency_admin', 'operations_manager'];

    public function index(Request $r, SystemHealthService $health): Response
    {
        /** @var User $user */
        $user = $r->user();
        $orgId = TenantContext::organizationId();
        abort_unless($user->is_system_admin || ($orgId && in_array($user->roleIn($orgId), self::ADMIN_ROLES, true)), 403);

        $checks = $health->checks(TenantContext::tenantId());
        $order = ['ok' => 0, 'warn' => 1, 'not_configured' => 1, 'unknown' => 2, 'down' => 3];
        $worst = collect($checks)->max(fn ($c) => $order[$c['status']] ?? 0);
        $overall = array_search($worst, $order, true) ?: 'ok';

        return Inertia::render('Ops/SystemHealth', [
            'checks' => $checks,
            'overall' => in_array($overall, ['down', 'unknown'], true) ? $overall : ($worst >= 1 ? 'warn' : 'ok'),
            'checkedAt' => now()->format('Y-m-d H:i:s'),
        ]);
    }
}
