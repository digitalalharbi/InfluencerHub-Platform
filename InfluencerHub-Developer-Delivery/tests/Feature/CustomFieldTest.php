<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, CustomFieldDefinition, CustomFieldOption};
use App\Domain\CRM\Services\CustomFieldService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 3 — الحقول المخصّصة: تحقق لكل نوع + خيارات select/multiselect + قراءة مُحوّلة. */
class CustomFieldTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private array $ctx;
    protected function setUp(): void
    {
        parent::setUp();
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'C', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        TenantContext::set($t->id);
        $this->ctx = ['tenant' => $t, 'client' => $client];
    }

    private function def(string $type, array $opts = [], bool $required = false): CustomFieldDefinition
    {
        $def = CustomFieldDefinition::create(['tenant_id' => $this->ctx['tenant']->id, 'entity_type' => 'client',
            'key' => Str::random(8), 'label' => "حقل {$type}", 'type' => $type, 'is_required' => $required]);
        foreach ($opts as $o) {
            CustomFieldOption::create(['tenant_id' => $this->ctx['tenant']->id, 'definition_id' => $def->id, 'value' => $o, 'label' => $o]);
        }
        return $def;
    }

    public function test_number_validates_and_reads_back_numeric(): void
    {
        $svc = app(CustomFieldService::class);
        $def = $this->def('number');
        $svc->setValue($def, $this->ctx['client'], '42');
        $this->assertSame(42, $svc->getValue($def, $this->ctx['client']));
        $this->expectException(\RuntimeException::class);
        $svc->setValue($def, $this->ctx['client'], 'notnum');
    }

    public function test_boolean_reads_back_bool(): void
    {
        $svc = app(CustomFieldService::class);
        $def = $this->def('boolean');
        $svc->setValue($def, $this->ctx['client'], true);
        $this->assertTrue($svc->getValue($def, $this->ctx['client']));
    }

    public function test_email_and_url_and_phone_reject_garbage(): void
    {
        $svc = app(CustomFieldService::class);
        $this->assertNotNull($svc->setValue($this->def('email'), $this->ctx['client'], 'a@b.com'));
        $this->assertNotNull($svc->setValue($this->def('url'), $this->ctx['client'], 'https://x.co'));
        $this->assertNotNull($svc->setValue($this->def('phone'), $this->ctx['client'], '+966501234567'));
        $this->expectException(\RuntimeException::class);
        $svc->setValue($this->def('email'), $this->ctx['client'], 'nope');
    }

    public function test_select_enforces_options(): void
    {
        $svc = app(CustomFieldService::class);
        $def = $this->def('select', ['red', 'blue']);
        $svc->setValue($def, $this->ctx['client'], 'red');
        $this->assertSame('red', $svc->getValue($def, $this->ctx['client']));
        $this->expectException(\RuntimeException::class);
        $svc->setValue($def, $this->ctx['client'], 'green'); // ليس ضمن الخيارات
    }

    public function test_multiselect_stores_json_and_reads_array(): void
    {
        $svc = app(CustomFieldService::class);
        $def = $this->def('multiselect', ['a', 'b', 'c']);
        $svc->setValue($def, $this->ctx['client'], ['a', 'c']);
        $this->assertSame(['a', 'c'], $svc->getValue($def, $this->ctx['client']));
        $this->expectException(\RuntimeException::class);
        $svc->setValue($def, $this->ctx['client'], ['a', 'z']); // z غير مسموح
    }

    public function test_required_field_rejects_empty(): void
    {
        $svc = app(CustomFieldService::class);
        $def = $this->def('text', [], required: true);
        $this->expectException(\RuntimeException::class);
        $svc->setValue($def, $this->ctx['client'], '');
    }

    public function test_datetime_normalizes(): void
    {
        $svc = app(CustomFieldService::class);
        $def = $this->def('datetime');
        $row = $svc->setValue($def, $this->ctx['client'], '2026-07-16 09:30:00');
        $this->assertStringContainsString('2026-07-16', $row->value);
    }

    public function test_value_is_upserted_not_duplicated(): void
    {
        $svc = app(CustomFieldService::class);
        $def = $this->def('text');
        $svc->setValue($def, $this->ctx['client'], 'first');
        $svc->setValue($def, $this->ctx['client'], 'second');
        $this->assertSame('second', $svc->getValue($def, $this->ctx['client']));
        $this->assertEquals(1, $def->values()->count()); // صف واحد فقط
    }
}
