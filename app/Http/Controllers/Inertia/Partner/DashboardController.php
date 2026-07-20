<?php

namespace App\Http\Controllers\Inertia\Partner;

use App\Domain\Partners\Enums\PartnerScope;
use App\Domain\Partners\Models\PartnerClientLink;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * لوحة بوابة الشريك (React/Inertia) — الروابط النشطة بنطاقاتها فقط (scoped). لا بيانات وهمية.
 * معزول على الوكالة الشريكة النشِطة (EnsurePartnerMember).
 */
class DashboardController extends Controller
{
    public function index(Request $r): Response
    {
        $a = $r->attributes->get('activeAgency');
        $scopeLabels = PartnerScope::labels();

        $links = PartnerClientLink::with('client', 'brand')->where('external_agency_id', $a->id)->where('status', 'active')->get();
        $clientIds = $links->pluck('client_id')->unique();
        $openRequests = ServiceRequest::where('requester_type', 'partner')->where('requester_agency_id', $a->id)
            ->whereIn('status', ServiceRequest::OPEN_STATUSES)->count();

        return Inertia::render('PartnerPortal/Dashboard', [
            'agency' => ['name' => $a->name, 'number' => $a->agency_number],
            'stats' => [
                'clients' => $clientIds->count(),
                'links' => $links->count(),
                'openRequests' => $openRequests,
            ],
            'links' => $links->map(fn (PartnerClientLink $l) => [
                'id' => $l->id,
                'client' => $l->client?->display_name ?? '—',
                'brand' => $l->brand?->name,
                'scopes' => collect($l->scopes ?? [])->map(fn ($s) => $scopeLabels[$s] ?? $s)->values()->all(),
            ])->values(),
        ]);
    }
}
