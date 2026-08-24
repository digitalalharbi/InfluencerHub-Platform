<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Dashboard\OperationalDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «عملي» (React/Inertia) — نقطة البداية اليومية: كل ما يحتاج إجراء المستخدم الآن،
 * مرتّبًا حسب الأولوية، من بيانات فعلية. يستعمل محرّك OperationalDashboard::myWork
 * نفسه الذي تستعمله لوحة التحكم، فلا تتكرّر نسختان من «المطلوب مني الآن»:
 *  - لوحة التحكم = نظرة عمل شاملة.  - عملي = ما عليّ أنا.  - الإشعارات = ما حدث.
 * Policy(viewAny Client)، معزولة.
 */
class MyTasksController extends Controller
{
    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Client::class);
        $oid = TenantContext::organizationId();

        $data = $oid
            ? (new OperationalDashboard($r->user(), $oid))->personalWork()
            : ['role' => null, 'brief' => ['tasks' => 0, 'approvals' => 0, 'overdue' => 0, 'total' => 0], 'myWork' => []];

        return Inertia::render('MyTasks/Index', $data);
    }
}
