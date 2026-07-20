<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مهامي (React/Inertia) — تجميع صادق لما يحتاج إجراء المستخدم الآن من بيانات فعلية.
 * لا أرقام وهمية؛ كل عنصر يرتبط بصفحته. Policy(viewAny Client)، معزولة.
 */
class MyTasksController extends Controller
{
    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Client::class);
        $user = $r->user();
        $role = TenantContext::organizationId() ? $user->roleIn(TenantContext::organizationId()) : null;
        $canReview = CrmAbilities::can($role, CrmAbilities::WRITE);

        // طلبات مُسنَدة إليّ ومفتوحة
        $myRequests = ServiceRequest::with('client')->where('assigned_to', $user->id)
            ->whereIn('status', ServiceRequest::OPEN_STATUSES)->latest()->limit(20)->get()
            ->map(fn (ServiceRequest $s) => [
                'id' => $s->id, 'title' => $s->title, 'client' => $s->client?->display_name,
                'status' => $s->status, 'statusLabel' => __("statuses.{$s->status}"), 'statusTone' => __("statuses.tone.{$s->status}"),
                'link' => "/service-requests/{$s->id}",
                'overdue' => $s->due_at && in_array($s->status, ServiceRequest::OPEN_STATUSES, true) && $s->due_at->isPast(),
            ]);

        // محتوى بانتظار مراجعة الوكالة
        $contentReview = ContentItem::with('campaign')->where('status', 'agency_review')->latest()->limit(20)->get()
            ->map(fn (ContentItem $c) => [
                'id' => $c->id, 'title' => $c->title, 'campaign' => $c->campaign?->name,
                'link' => "/content/{$c->id}",
            ]);

        // علامات/عملاء بانتظار المراجعة (لأصحاب صلاحية المراجعة فقط)
        $brandReviews = $canReview
            ? Brand::with('client')->whereIn('status', ['submitted', 'under_review'])->latest()->limit(20)->get()
                ->map(fn (Brand $b) => ['id' => $b->id, 'title' => $b->name, 'client' => $b->client?->display_name, 'link' => "/brands/{$b->id}"])
            : collect();

        return Inertia::render('MyTasks/Index', [
            'myRequests' => $myRequests->values(),
            'contentReview' => $contentReview->values(),
            'brandReviews' => $brandReviews->values(),
            'canReview' => $canReview,
        ]);
    }
}
