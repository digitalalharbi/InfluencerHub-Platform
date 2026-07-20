<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\Campaigns\Models\{Campaign, CampaignShortlistVersion};
use App\Domain\Contracts\Models\Contract;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * لوحة بوابة العميل (React/Inertia) — بيانات فعلية فقط، معزولة على العميل النشِط (EnsureClientMember).
 * تُبرز "ما يحتاج قرارك الآن": موافقات محتوى + توقيع عقود + قرارات ترشيح + طلبات مفتوحة.
 */
class DashboardController extends Controller
{
    public function index(Request $r): Response
    {
        /** @var \App\Domain\CRM\Models\Client $c */
        $c = $r->attributes->get('activeClient');
        $c->loadCount(['brands', 'contacts', 'documents', 'members']);

        $fields = ['legal_name', 'email', 'phone', 'sector', 'city', 'commercial_registration_number', 'tax_number'];
        $filled = collect($fields)->filter(fn ($f) => ! empty($c->$f))->count();
        $completion = (int) round($filled / count($fields) * 100);


        $contentPending = ContentItem::where('client_id', $c->id)->where('status', 'client_review')->count();
        $contractsPending = Contract::where('party_type', 'client')->where('client_id', $c->id)->where('status', 'sent')->count();
        $requestsOpen = ServiceRequest::where('requester_type', 'client')->where('requester_client_id', $c->id)
            ->whereIn('status', ServiceRequest::OPEN_STATUSES)->count();

        $campaignIds = Campaign::where('client_id', $c->id)->pluck('id');
        $shortlistPending = CampaignShortlistVersion::whereHas('shortlist', fn ($q) => $q->whereIn('campaign_id', $campaignIds))
            ->whereIn('status', ['submitted', 'partially_approved'])
            ->whereHas('items', fn ($q) => $q->where(fn ($w) => $w->whereNull('client_decision')->orWhere('client_decision', 'pending')))
            ->count();

        $activeCampaigns = Campaign::where('client_id', $c->id)->whereNotIn('status', ['draft', 'completed', 'cancelled'])->count();

        $recent = Campaign::withCount('deliverables')->where('client_id', $c->id)->whereNotIn('status', ['draft'])
            ->latest()->limit(5)->get()->map(fn (Campaign $cm) => [
                'id' => $cm->id, 'name' => $cm->name, 'number' => $cm->campaign_number,
                'status' => $cm->status, 'statusLabel' => __("statuses.{$cm->status}"),
                'statusTone' => __("statuses.tone.{$cm->status}"),
                'deliverables' => (int) $cm->deliverables_count,
                'budgetMinor' => (int) $cm->budget_minor,
            ]);


        return Inertia::render('ClientPortal/Dashboard', [
            'client' => [
                'name' => $c->display_name, 'sector' => $c->sector, 'completion' => $completion,
                'brands' => $c->brands_count, 'team' => $c->members_count,
                'documents' => $c->documents_count, 'contacts' => $c->contacts_count,
            ],
            'pending' => [
                ['key' => 'content', 'label' => 'محتوى بانتظار اعتمادك', 'count' => $contentPending, 'icon' => 'image', 'link' => '/content'],
                ['key' => 'contracts', 'label' => 'عقود بانتظار توقيعك', 'count' => $contractsPending, 'icon' => 'file-text', 'link' => '/contracts'],
                ['key' => 'shortlist', 'label' => 'ترشيحات بانتظار قرارك', 'count' => $shortlistPending, 'icon' => 'users', 'link' => '/campaigns'],
                ['key' => 'requests', 'label' => 'طلبات مفتوحة قيد التنفيذ', 'count' => $requestsOpen, 'icon' => 'inbox', 'link' => '/requests'],
            ],
            'stats' => [
                'activeCampaigns' => $activeCampaigns, 'brands' => $c->brands_count,
                'team' => $c->members_count, 'documents' => $c->documents_count,
            ],
            'recent' => $recent,
        ]);
    }
}
