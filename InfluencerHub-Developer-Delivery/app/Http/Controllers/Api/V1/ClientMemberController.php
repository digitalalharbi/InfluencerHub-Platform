<?php
namespace App\Http\Controllers\Api\V1;
use App\Domain\CRM\Actions\{InviteClientMember, ChangeClientMemberStatus, ChangeClientMemberRole};
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class ClientMemberController extends Controller {
    public function index(Client $client) {
        $this->authorize('managePortal', $client);
        return response()->json(['data' => $client->members()->latest()->get()]);
    }
    public function invite(Request $r, Client $client, InviteClientMember $action) {
        $this->authorize('managePortal', $client);
        $data = $r->validate(['email' => 'required|email|max:160', 'role' => 'required|string']);
        [$inv, $raw] = $action->handle($client, $data['email'], $data['role'], $r->user('sanctum'));
        // الرمز الخام يُعاد مرة واحدة فقط (لا يُخزَّن؛ المخزَّن هو الـHash)
        return response()->json(['data' => ['invitation_id' => $inv->id, 'token' => $raw]], 201);
    }
    public function updateStatus(Request $r, Client $client, ClientMember $member, ChangeClientMemberStatus $action) {
        $this->authorize('managePortal', $client);
        abort_unless($member->client_id === $client->id, 404);
        $data = $r->validate(['action' => 'required|in:suspend,reactivate,revoke']);
        return response()->json(['data' => $action->handle($member, $data['action'], $r->user('sanctum'))]);
    }
    public function updateRole(Request $r, Client $client, ClientMember $member, ChangeClientMemberRole $action) {
        $this->authorize('managePortal', $client);
        abort_unless($member->client_id === $client->id, 404);
        $data = $r->validate(['role' => 'required|string']);
        return response()->json(['data' => $action->handle($member, $data['role'], $r->user('sanctum'))]);
    }
}
