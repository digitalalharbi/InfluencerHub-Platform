<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Enums\PartnerScope;
use App\Domain\Partners\Models\{ExternalAgency, PartnerClientLink};
use App\Domain\Partners\Services\{ExternalAgencyWorkflowService, InvitePartnerMember};
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الوكالات الشريكة (React/Inertia) — منقولة من PartnerAgencyController (Blade)
 * بنفس الصلاحيات وسير العمل (ExternalAgencyWorkflowService) والروابط المُنطّقة.
 *
 * تنبيه الصلاحيات — الفصل مقصود ومطابق لنسخة Blade:
 * `update` للتحرير والإرسال وبدء المراجعة، و`manage` للاعتماد والتعليق
 * وطلب التعديل والدعوات والروابط (قرارات تفتح وصولًا لطرف خارجي).
 */
class PartnersController extends Controller
{
    /** الإجراءات المتاحة لكل حالة → [action, label, tone, needsReason]. */
    private const ACTIONS = [
        'draft' => [['submit', 'إرسال للمراجعة', 'primary', false]],
        'submitted' => [['start', 'بدء المراجعة', 'primary', false]],
        'under_review' => [['approve', 'اعتماد الوكالة', 'primary', false], ['request-changes', 'طلب تعديل', 'ghost', true]],
        'changes_requested' => [['submit', 'إعادة الإرسال', 'primary', false]],
        'approved' => [['suspend', 'تعليق الوكالة', 'danger', true]],
        'suspended' => [['approve', 'إعادة الاعتماد', 'primary', false]],
        'archived' => [],
    ];

    /** الإجراءات التي تتطلّب صلاحية manage لا update. */
    private const MANAGE_ACTIONS = ['approve', 'request-changes', 'suspend'];

    public function index(Request $r): Response
    {
        $this->authorize('viewAny', ExternalAgency::class);

        $q = ExternalAgency::query()->withCount('members', 'links')->latest();
        if ($status = $r->query('status')) {
            $q->where('status', $status);
        }

        $agencies = $q->paginate(20)->withQueryString();
        $agencies->through(fn (ExternalAgency $a) => [
            'id' => $a->id, 'name' => $a->name, 'number' => $a->agency_number,
            'contact' => $a->contact_name, 'specialization' => $a->specialization,
            'members' => (int) $a->members_count, 'links' => (int) $a->links_count,
            'status' => $a->status,
            'statusLabel' => __('statuses.' . $a->status),
            'statusTone' => __('statuses.tone.' . $a->status),
            'needsReview' => in_array($a->status, ['submitted', 'under_review'], true),
        ]);

        $count = fn (array $st) => ExternalAgency::query()->whereIn('status', $st)->count();

        return Inertia::render('Partners/Index', [
            'agencies' => $agencies,
            'filters' => $r->only('status'),
            'summary' => [
                'total' => ExternalAgency::query()->count(),
                'needsReview' => $count(['submitted', 'under_review']),
                'approved' => $count(['approved']),
                'suspended' => $count(['suspended']),
                'draft' => $count(['draft']),
            ],
            'statusOptions' => collect(['draft', 'submitted', 'under_review', 'changes_requested', 'approved', 'suspended', 'archived'])
                ->mapWithKeys(fn (string $s) => [$s => __('statuses.' . $s)])->all(),
            'canCreate' => $r->user()->can('create', ExternalAgency::class),
        ]);
    }

    public function store(Request $r, ExternalAgencyWorkflowService $wf): RedirectResponse
    {
        $this->authorize('create', ExternalAgency::class);
        $agency = $wf->createDraft(TenantContext::tenantId(), $r->validate($this->rules()), $r->user()->id);

        return redirect(MountPrefix::path($r, "/partner-agencies/{$agency->id}"))
            ->with('ok', 'أُنشئت مسودة الوكالة الخارجية.');
    }

    public function show(Request $r, ExternalAgency $partnerAgency): Response
    {
        $this->authorize('view', $partnerAgency);
        $a = $partnerAgency->load('statusHistory', 'members.user', 'invitations', 'links.client', 'links.brand');
        $canUpdate = $r->user()->can('update', $a);
        $canManage = $r->user()->can('manage', $a);

        $actorNames = User::whereIn('id', $a->statusHistory->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $scopeLabels = PartnerScope::labels();

        $actions = collect(self::ACTIONS[$a->status] ?? [])
            ->filter(fn (array $act) => in_array($act[0], self::MANAGE_ACTIONS, true) ? $canManage : $canUpdate)
            ->values();

        return Inertia::render('Partners/Show', [
            'agency' => [
                'id' => $a->id, 'name' => $a->name, 'number' => $a->agency_number, 'legalName' => $a->legal_name,
                'contactName' => $a->contact_name, 'contactEmail' => $a->contact_email, 'contactPhone' => $a->contact_phone,
                'country' => $a->country_code, 'website' => $a->website, 'specialization' => $a->specialization,
                'notes' => $a->notes,
                'status' => $a->status,
                'statusLabel' => __('statuses.' . $a->status),
                'statusTone' => __('statuses.tone.' . $a->status),
                'isActivePartner' => $a->isActivePartner(),
                'editable' => $a->status === 'draft' || $a->status === 'changes_requested',
            ],
            'can' => ['update' => $canUpdate, 'manage' => $canManage],
            'actions' => $actions,
            'members' => $a->members->map(fn ($m) => [
                'name' => $m->user?->name ?? '—', 'email' => $m->user?->email,
                'role' => $m->role, 'status' => $m->status,
                'statusLabel' => __('statuses.' . $m->status), 'statusTone' => __('statuses.tone.' . $m->status),
            ])->values(),
            'invitations' => $a->invitations->map(fn ($i) => [
                'email' => $i->email, 'role' => $i->role, 'status' => $i->status,
                'expiresAt' => $i->expires_at?->format('Y-m-d'),
            ])->values(),
            'links' => $a->links->map(fn (PartnerClientLink $l) => [
                'id' => $l->id, 'client' => $l->client?->display_name, 'brand' => $l->brand?->name,
                'scopes' => collect($l->scopes ?? [])->map(fn ($s) => $scopeLabels[$s] ?? $s)->values(),
                'status' => $l->status, 'active' => $l->status === 'active',
            ])->values(),
            'history' => $a->statusHistory->sortByDesc('id')->values()->map(fn ($h) => [
                'from' => $h->from_status ? __('statuses.' . $h->from_status) : '—',
                'to' => __('statuses.' . $h->to_status),
                'by' => $actorNames[$h->actor_id] ?? '—',
                'reason' => $h->reason, 'at' => $h->occurred_at?->format('Y-m-d H:i'),
            ]),
            // خيارات الربط تُحمّل لمن يملك الإدارة فقط
            'clientOptions' => $canManage
                ? Client::orderBy('display_name')->get(['id', 'display_name'])
                    ->map(fn (Client $c) => ['id' => $c->id, 'name' => $c->display_name])->values()
                : [],
            'brandOptions' => $canManage
                ? Brand::orderBy('name')->get(['id', 'name', 'client_id'])
                    ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name, 'clientId' => $b->client_id])->values()
                : [],
            'scopeOptions' => $scopeLabels,
        ]);
    }

    public function update(Request $r, ExternalAgency $partnerAgency, ExternalAgencyWorkflowService $wf): RedirectResponse
    {
        $this->authorize('update', $partnerAgency);
        try {
            $wf->updateDraft($partnerAgency, $r->validate($this->rules()), $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُفظت التعديلات.');
    }

    public function action(Request $r, ExternalAgency $partnerAgency, string $action, ExternalAgencyWorkflowService $wf): RedirectResponse
    {
        $this->authorize(in_array($action, self::MANAGE_ACTIONS, true) ? 'manage' : 'update', $partnerAgency);
        $reason = $action === 'request-changes'
            ? $r->validate(['reason' => 'required|string|max:500'])['reason']
            : $r->input('reason');

        try {
            match ($action) {
                'submit' => $wf->submit($partnerAgency, $r->user()->id),
                'start' => $wf->startReview($partnerAgency, $r->user()->id),
                'approve' => $wf->approve($partnerAgency, $r->user()->id, $reason),
                'request-changes' => $wf->requestChanges($partnerAgency, $r->user()->id, $reason),
                'suspend' => $wf->suspend($partnerAgency, $r->user()->id, $reason),
                default => abort(404),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُدّثت حالة الوكالة الشريكة.');
    }

    public function invite(Request $r, ExternalAgency $partnerAgency, InvitePartnerMember $action): RedirectResponse
    {
        $this->authorize('manage', $partnerAgency);
        $data = $r->validate(['email' => 'required|email|max:160', 'role' => 'required|string']);

        try {
            [, $raw] = $action->handle($partnerAgency, $data['email'], $data['role'], $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['member' => $e->getMessage()]);
        }

        // الرمز يُعرض مرة واحدة فقط — لا يُخزَّن خامًا
        return back()->with('ok', 'أُرسلت الدعوة.')->with('invite_token', $raw);
    }

    public function linkClient(Request $r, ExternalAgency $partnerAgency): RedirectResponse
    {
        $this->authorize('manage', $partnerAgency);
        // لا ربط قبل الاعتماد: الربط يفتح وصولًا فعليًا لبيانات العميل
        abort_unless($partnerAgency->isActivePartner(), 422, 'لا يمكن الربط قبل اعتماد الوكالة.');
        $data = $r->validate([
            'client_id' => 'required|integer',
            'brand_id' => 'nullable|integer',
            'scopes' => 'array',
            'scopes.*' => 'in:' . implode(',', PartnerScope::values()),
        ]);

        // انتماء العميل/العلامة للمستأجر — fail-closed
        $client = Client::findOrFail($data['client_id']);
        if (! empty($data['brand_id'])) {
            Brand::where('id', $data['brand_id'])->where('client_id', $client->id)->firstOrFail();
        }

        PartnerClientLink::updateOrCreate(
            [
                'tenant_id' => $partnerAgency->tenant_id, 'external_agency_id' => $partnerAgency->id,
                'client_id' => $client->id, 'brand_id' => $data['brand_id'] ?? null,
            ],
            ['scopes' => array_values($data['scopes'] ?? []), 'status' => 'active', 'created_by' => $r->user()->id],
        );
        AuditLogger::log('partner_link.created', $partnerAgency,
            ['client_id' => $client->id, 'brand_id' => $data['brand_id'] ?? null], $partnerAgency->tenant_id, $r->user()->id);

        return back()->with('ok', 'أُضيف الربط المُنطّق.');
    }

    public function revokeLink(Request $r, ExternalAgency $partnerAgency, int $link): RedirectResponse
    {
        $this->authorize('manage', $partnerAgency);
        $l = PartnerClientLink::where('id', $link)->where('external_agency_id', $partnerAgency->id)->firstOrFail();
        $l->update(['status' => 'revoked']);
        AuditLogger::log('partner_link.revoked', $partnerAgency, ['link_id' => $l->id], $partnerAgency->tenant_id, $r->user()->id);

        return back()->with('ok', 'أُلغي الربط.');
    }

    /** @return array<string,string> */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:160',
            'legal_name' => 'nullable|string|max:160',
            'contact_name' => 'nullable|string|max:120',
            'contact_email' => 'nullable|email|max:160',
            'contact_phone' => 'nullable|string|max:40',
            'country_code' => 'nullable|string|max:5',
            'website' => 'nullable|string|max:200',
            'specialization' => 'nullable|string|max:160',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
