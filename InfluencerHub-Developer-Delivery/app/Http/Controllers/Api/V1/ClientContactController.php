<?php
namespace App\Http\Controllers\Api\V1;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\{Client, ClientContact};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class ClientContactController extends Controller {
    private function rules(bool $partial = false): array {
        $r = $partial ? 'sometimes' : 'required';
        return ['name' => "$r|string|max:160", 'job_title' => 'nullable|string|max:120', 'department' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:160', 'phone' => 'nullable|string|max:30', 'whatsapp' => 'nullable|string|max:30',
            'is_primary' => 'nullable|boolean', 'preferred_channel' => 'nullable|string|max:30', 'notes' => 'nullable|string'];
    }
    public function index(Client $client) { $this->authorize('view', $client); return response()->json(['data' => $client->contacts()->latest()->get()]); }
    public function store(Request $r, Client $client) {
        $this->authorize('update', $client);
        $data = $r->validate($this->rules());
        $c = ClientContact::create($data + ['tenant_id' => $client->tenant_id, 'client_id' => $client->id]);
        AuditLogger::log('client_contact.created', $c, [], $client->tenant_id, $r->user('sanctum')->id);
        return response()->json(['data' => $c], 201);
    }
    public function update(Request $r, Client $client, ClientContact $contact) {
        $this->authorize('update', $client);
        abort_unless($contact->client_id === $client->id, 404);
        $contact->update($r->validate($this->rules(true)));
        AuditLogger::log('client_contact.updated', $contact, [], $client->tenant_id, $r->user('sanctum')->id);
        return response()->json(['data' => $contact->fresh()]);
    }
    public function destroy(Request $r, Client $client, ClientContact $contact) {
        $this->authorize('update', $client);
        abort_unless($contact->client_id === $client->id, 404);
        AuditLogger::log('client_contact.deleted', $contact, [], $client->tenant_id, $r->user('sanctum')->id);
        $contact->delete();
        return response()->json(['message' => 'تم الحذف']);
    }
}
