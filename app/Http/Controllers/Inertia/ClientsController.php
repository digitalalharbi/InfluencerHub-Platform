<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Actions\{ArchiveClient, CreateClient};
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use App\Support\Analytics\ClientAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * قائمة العملاء (React/Inertia) — نفس منطق ClientWebController@index وبياناته الحقيقية.
 * Enterprise Data View: مؤشرات مالية/تشغيلية + شرائح + بحث/فلاتر + إجراء مطلوب لكل عميل.
 * Policy(viewAny)، معزولة بالمستأجر.
 */
class ClientsController extends Controller
{
    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Client::class);

        $q = Client::query()->with('accountManager')->withCount(['brands', 'campaigns'])->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(function ($w) use ($s) {
                $w->where('display_name', 'ilike', "%{$s}%")->orWhere('legal_name', 'ilike', "%{$s}%")
                  ->orWhere('client_number', 'ilike', "%{$s}%")->orWhere('sector', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")->orWhere('phone', 'ilike', "%{$s}%")
                  ->orWhereHas('brands', fn ($b) => $b->where('name', 'ilike', "%{$s}%"))
                  ->orWhereHas('accountManager', fn ($u) => $u->where('name', 'ilike', "%{$s}%"));
            });
        }
        if ($v = $r->query('status')) $q->where('status', $v);
        if ($v = $r->query('sector')) $q->where('sector', $v);
        if ($v = $r->query('manager')) $q->where('account_manager_id', $v);
        ClientAnalytics::applySegment($q, $r->query('seg'));

        $clients = $q->paginate(15)->withQueryString();
        $metrics = ClientAnalytics::forPage($clients->getCollection());

        $statusLabels = ['lead' => 'مهتم', 'qualified' => 'مؤهّل', 'active' => 'نشط', 'inactive' => 'غير نشط', 'suspended' => 'موقوف', 'archived' => 'مؤرشف'];
        $statusTones = ['lead' => 'submitted', 'qualified' => 'under_review', 'active' => 'active', 'inactive' => 'archived', 'suspended' => 'rejected', 'archived' => 'archived'];

        $clients->through(fn (Client $c) => [
            'id' => $c->id,
            'name' => $c->display_name,
            'number' => $c->client_number,
            'sector' => $c->sector,
            'manager' => $c->accountManager?->name,
            'brands' => (int) $c->brands_count,
            'status' => $c->status,
            'statusLabel' => $statusLabels[$c->status] ?? $c->status,
            'statusTone' => $statusTones[$c->status] ?? 'draft',
            'revenueMinor' => (int) ($metrics[$c->id]['revenue_minor'] ?? 0),
            'activeCampaigns' => (int) ($metrics[$c->id]['active_campaigns'] ?? 0),
            'completion' => (int) ($metrics[$c->id]['completion'] ?? 0),
            'isVip' => (bool) ($metrics[$c->id]['is_vip'] ?? false),
            'needsAction' => (int) ($metrics[$c->id]['needs_action'] ?? 0),
        ]);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'summary' => ClientAnalytics::summary(),
            'operational' => ClientAnalytics::operational(),
            'filters' => $r->only('q', 'status', 'sector', 'manager', 'seg'),
            'sectors' => Client::query()->whereNotNull('sector')->distinct()->orderBy('sector')->pluck('sector')->values(),
            'managers' => User::whereIn('id', Client::query()->whereNotNull('account_manager_id')->distinct()->pluck('account_manager_id'))
                ->orderBy('name')->get(['id', 'name']),
            'canCreate' => $r->user()->can('create', Client::class),
        ]);
    }

    public function store(Request $r, CreateClient $action)
    {
        $this->authorize('create', Client::class);
        $data = $r->validate([
            'display_name' => 'required|string|max:200',
            'type' => 'nullable|in:company,brand_owner,government,nonprofit,agency,individual,other',
            'status' => 'nullable|in:lead,qualified,active,inactive,suspended',
            'email' => 'nullable|email|max:200', 'phone' => 'nullable|string|max:30', 'sector' => 'nullable|string|max:120',
        ]);
        try {
            $client = $action->handle($this->org(), $data, $r->user());
        } catch (\App\Domain\Billing\Exceptions\EntitlementLimitExceeded) {
            return back()->withErrors(['display_name' => 'تم بلوغ حد العملاء في خطتك.']);
        }
        return redirect(MountPrefix::path($r, "/clients/{$client->id}"))->with('ok', 'تم إنشاء العميل.');
    }

    /**
     * تحديث بيانات العميل وحالته.
     *
     * لم يكن للعميل مسار تحديث أصلًا: يُنشأ «مهتمًّا» فيبقى كذلك أبدًا، وجاهزية
     * الحملة تشترط عميلًا نشطًا — فتُحجب الحملة بشرط لا سبيل إلى رفعه.
     */
    public function update(Request $r, Client $client)
    {
        $this->authorize('update', $client);

        $data = $r->validate([
            'display_name' => 'sometimes|required|string|max:190',
            'status' => 'sometimes|required|string|in:lead,qualified,active,inactive,suspended',
            'sector' => 'sometimes|nullable|string|max:120',
            'email' => 'sometimes|nullable|email|max:190',
        ], [], ['display_name' => 'اسم العميل', 'status' => 'الحالة']);

        $before = $client->only(array_keys($data));
        $client->update($data);

        \App\Domain\Audit\Services\AuditLogger::log(
            'client.updated', $client, ['from' => $before, 'to' => $data],
            (int) $client->tenant_id, (int) $r->user()->id,
        );

        return back()->with('ok', 'حُدّثت بيانات العميل.');
    }

    public function destroy(Request $r, Client $client, ArchiveClient $action)
    {
        $this->authorize('delete', $client);
        $action->handle($this->org(), $client);
        return redirect(MountPrefix::path($r, '/clients'))->with('ok', 'تمت أرشفة العميل.');
    }

    private function org(): ?Organization
    {
        return TenantContext::organizationId() ? Organization::find(TenantContext::organizationId()) : null;
    }
}
