<?php
namespace App\Http\Controllers\Api\V1;
use App\Domain\CRM\Actions\{CreateClient, ArchiveClient, RestoreClient};
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClientController extends Controller {
    private function org(): ?Organization {
        return TenantContext::organizationId() ? Organization::find(TenantContext::organizationId()) : null;
    }

    public function index(Request $r) {
        $this->authorize('viewAny', Client::class);
        $q = Client::query()->latest();
        if ($s = $r->query('status')) $q->where('status', $s);
        if ($s = $r->query('type'))   $q->where('type', $s);
        if ($s = $r->query('q'))      $q->where('display_name', 'ilike', "%{$s}%");
        return response()->json($q->paginate((int) $r->query('per_page', 25)));
    }

    public function store(Request $r, CreateClient $action) {
        $this->authorize('create', Client::class);
        $org = $this->org();
        abort_if(! $org, 422, 'لا سياق مؤسسة');
        $data = $r->validate([
            'display_name' => 'required|string|max:200',
            'legal_name' => 'nullable|string|max:200',
            'type' => 'nullable|in:company,brand_owner,government,nonprofit,agency,individual,other',
            'status' => 'nullable|in:lead,qualified,active,inactive,suspended',
            'email' => 'nullable|email|max:200', 'phone' => 'nullable|string|max:30',
            'sector' => 'nullable|string|max:120', 'commercial_registration_number' => 'nullable|string|max:30',
            'tax_number' => 'nullable|string|max:30', 'vat_registered' => 'nullable|boolean',
        ]);
        $client = $action->handle($org, $data, $r->user('sanctum'));
        return response()->json(['data' => $client], 201);
    }

    public function show(Client $client) {
        $this->authorize('view', $client);
        return response()->json(['data' => $client->load('brands', 'contacts')]);
    }

    public function update(Request $r, Client $client) {
        $this->authorize('update', $client);
        $data = $r->validate(['display_name' => 'sometimes|string|max:200', 'sector' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:200', 'phone' => 'nullable|string|max:30', 'website' => 'nullable|string|max:200']);
        $client->update($data + ['updated_by' => $r->user('sanctum')->id]);
        \App\Domain\Audit\Services\AuditLogger::log('client.updated', $client, array_keys($data));
        return response()->json(['data' => $client->fresh()]);
    }

    public function destroy(Request $r, Client $client, ArchiveClient $action) {
        $this->authorize('delete', $client);
        $action->handle($this->org(), $client);
        return response()->json(['message' => 'تمت الأرشفة']);
    }

    public function restore(Request $r, int $client, RestoreClient $action) {
        $model = Client::withTrashed()->findOrFail($client);
        $action->handle($this->org(), $model, $r->input('status', 'active'));
        return response()->json(['data' => $model->fresh()]);
    }
}
