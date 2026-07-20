<?php
namespace App\Http\Controllers\Api\V1;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Actions\CreateBrand;
use App\Domain\CRM\Models\{Brand, Client};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class BrandController extends Controller {
    public function index(Request $r, Client $client) {
        $this->authorize('view', $client);
        return response()->json(['data' => $client->brands()->latest()->get()]);
    }
    public function store(Request $r, Client $client, CreateBrand $action) {
        $this->authorize('update', $client);
        $data = $r->validate(['name' => 'required|string|max:160', 'sector' => 'nullable|string|max:120',
            'website' => 'nullable|string|max:200', 'description' => 'nullable|string', 'status' => 'nullable|string|max:30']);
        return response()->json(['data' => $action->handle($client, $data, $r->user('sanctum'))], 201);
    }
    public function show(Client $client, Brand $brand) {
        $this->authorize('view', $client);
        abort_unless($brand->client_id === $client->id, 404);
        return response()->json(['data' => $brand]);
    }
    public function update(Request $r, Client $client, Brand $brand) {
        $this->authorize('update', $client);
        abort_unless($brand->client_id === $client->id, 404);
        $data = $r->validate(['name' => 'sometimes|string|max:160', 'sector' => 'nullable|string|max:120',
            'website' => 'nullable|string|max:200', 'description' => 'nullable|string', 'status' => 'nullable|string|max:30']);
        $brand->update($data + ['updated_by' => $r->user('sanctum')->id]);
        AuditLogger::log('brand.updated', $brand, array_keys($data), $brand->tenant_id, $r->user('sanctum')->id);
        return response()->json(['data' => $brand->fresh()]);
    }
    public function destroy(Request $r, Client $client, Brand $brand) {
        $this->authorize('delete', $client);
        abort_unless($brand->client_id === $client->id, 404);
        AuditLogger::log('brand.deleted', $brand, [], $brand->tenant_id, $r->user('sanctum')->id);
        $brand->delete();
        return response()->json(['message' => 'تم الحذف']);
    }
}
