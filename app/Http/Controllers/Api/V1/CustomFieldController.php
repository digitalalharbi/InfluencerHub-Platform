<?php
namespace App\Http\Controllers\Api\V1;
use App\Domain\CRM\Enums\CustomFieldType;
use App\Domain\CRM\Models\{Client, CustomFieldDefinition, CustomFieldOption};
use App\Domain\CRM\Services\CustomFieldService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** تعريفات الحقول المخصّصة + ضبط قيمها على عميل. */
class CustomFieldController extends Controller {
    public function index(Request $r) {
        $entity = $r->query('entity_type', 'client');
        return response()->json(['data' => CustomFieldDefinition::where('entity_type', $entity)->orderBy('sort_order')->with('options')->get()]);
    }
    public function store(Request $r) {
        $data = $r->validate([
            'entity_type' => 'required|in:client,brand', 'key' => 'required|string|max:60', 'label' => 'required|string|max:160',
            'type' => ['required', 'in:' . implode(',', CustomFieldType::values())], 'is_required' => 'nullable|boolean',
            'options' => 'nullable|array', 'options.*' => 'string|max:120',
        ]);
        $def = CustomFieldDefinition::create([
            'tenant_id' => TenantContext::tenantId(), 'entity_type' => $data['entity_type'], 'key' => $data['key'],
            'label' => $data['label'], 'type' => $data['type'], 'is_required' => $data['is_required'] ?? false,
        ]);
        foreach (($data['options'] ?? []) as $i => $opt) {
            CustomFieldOption::create(['tenant_id' => $def->tenant_id, 'definition_id' => $def->id, 'value' => $opt, 'label' => $opt, 'sort_order' => $i]);
        }
        return response()->json(['data' => $def->load('options')], 201);
    }
    public function setValue(Request $r, Client $client, CustomFieldDefinition $definition, CustomFieldService $svc) {
        $this->authorize('update', $client);
        abort_unless($definition->entity_type === 'client', 422, 'التعريف ليس لكيان العميل');
        abort_unless($definition->tenant_id === $client->tenant_id, 404); // عزل المستأجر
        try {
            $row = $svc->setValue($definition, $client, $r->input('value'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => 'custom_field_invalid'], 422);
        }
        return response()->json(['data' => $row]);
    }
}
