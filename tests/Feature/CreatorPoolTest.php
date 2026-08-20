<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Models\{PoolCreator, PoolRecommendation};
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * قاعدة مبدعي مدير النظام.
 *
 * الأهمّ هنا حارس الاستبعاد: مهما احتوى ملفّ الاستيراد، لا يصل القاعدة إلا
 * الحقول المسموحة — لا اسم مصدر/علامة، ولا ترخيص، ولا بنك/IBAN، ولا موظّف.
 */
class CreatorPoolTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6) . '@ex.com',
            'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true])->save();

        return $u;
    }

    private function columns(): array
    {
        return \Schema::getColumnListing('admin_creator_pool');
    }

    /** الجدول نفسه لا يحوي أيّ عمود مستبعَد — دفاع في العمق. */
    public function test_the_table_has_no_excluded_columns(): void
    {
        $cols = $this->columns();
        foreach (['license', 'رخصة', 'iban', 'bank', 'employee', 'account_holder', 'shipment', 'smartcode', 'smart_code'] as $forbidden) {
            foreach ($cols as $c) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $c,
                    "العمود «{$c}» يشبه حقلًا مستبعَدًا");
            }
        }
    }

    /** حارس الاستيراد يُسقط أي مفتاح مستبعَد قبل الكتابة. */
    public function test_import_drops_excluded_fields(): void
    {
        Storage::fake('local');
        $rows = [[
            'name' => 'مبدع', 'platform' => 'tiktok', 'account_url' => 'https://tiktok.com/@x',
            'followers' => 1000, 'tier' => 'A',
            // حقول مستبعَدة يجب ألّا تصل:
            'license_number' => '686983', 'iban' => 'SA00...', 'bank' => 'الراجحي',
            'employee' => 'ماجد', 'source_company' => 'سمارت كود', 'shipment' => '123',
        ]];
        Storage::disk('local')->put('pool/pool.json', json_encode($rows));

        $this->artisan('pool:import', ['--fresh' => true])->assertSuccessful();

        $created = PoolCreator::first();
        $this->assertNotNull($created);
        $this->assertSame('مبدع', $created->name);

        // لا أثر لأي قيمة مستبعَدة في كامل الصفّ المحفوظ
        $rowJson = json_encode(DB::table('admin_creator_pool')->first(), JSON_UNESCAPED_UNICODE);
        foreach (['686983', 'SA00', 'الراجحي', 'ماجد', 'سمارت', 'كود', '123'] as $leak) {
            $this->assertStringNotContainsString($leak, $rowJson, "تسرّبت قيمة مستبعَدة: {$leak}");
        }
    }

    public function test_import_dedupes_by_platform_and_url(): void
    {
        Storage::fake('local');
        $rows = [
            ['name' => 'أوّل', 'platform' => 'tiktok', 'account_url' => 'https://t/@a'],
            ['name' => 'مكرّر', 'platform' => 'tiktok', 'account_url' => 'https://t/@a'],
        ];
        Storage::disk('local')->put('pool/pool.json', json_encode($rows));
        $this->artisan('pool:import', ['--fresh' => true])->assertSuccessful();

        $this->assertSame(1, PoolCreator::count());
    }

    // ===== الوصول: مدير النظام وحده =====

    public function test_only_system_admin_reaches_the_pool(): void
    {
        $plain = User::create(['name' => 'عادي', 'email' => 'p@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        $this->get('/beta/admin/creator-pool')->assertRedirect();          // ضيف
        $this->actingAs($plain)->get('/beta/admin/creator-pool')->assertForbidden(); // غير مدير
        $this->actingAs($this->admin())->get('/beta/admin/creator-pool')->assertOk();
    }

    // ===== الحذف (كِلّ سويتش) =====

    public function test_purge_requires_typed_confirmation(): void
    {
        PoolCreator::create(['name' => 'ب', 'platform' => 'x', 'account_url' => 'https://x/1']);

        $this->actingAs($this->admin())->from('/beta/admin/creator-pool')
            ->post('/beta/admin/creator-pool/purge', ['confirm' => 'خطأ'])
            ->assertSessionHasErrors('confirm');
        $this->assertSame(1, PoolCreator::count(), 'حُذفت القاعدة بتأكيد خاطئ');
    }

    public function test_purge_wipes_the_pool_with_correct_confirmation(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pool/pool.json', '[]');
        PoolCreator::create(['name' => 'ب', 'platform' => 'x', 'account_url' => 'https://x/1']);

        $this->actingAs($this->admin())
            ->post('/beta/admin/creator-pool/purge', ['confirm' => 'حذف'])
            ->assertRedirect();

        $this->assertSame(0, PoolCreator::count());
        // يمسح ملفّ الاستيراد أيضًا فلا يبقى أثر على القرص
        $this->assertFalse(Storage::disk('local')->exists('pool/pool.json'));
    }

    // ===== التحويل إلى العميل =====

    private function client(): Client
    {
        $t = Tenant::create(['name' => 'و', 'slug' => \Illuminate\Support\Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'عميل', 'status' => 'active']);
        TenantContext::reset();

        return $c;
    }

    /** التحويل يأخذ نسخة بلا جوّال: التواصل شأن المدير لا العميل. */
    public function test_transfer_snapshots_without_the_phone(): void
    {
        $client = $this->client();
        $p = PoolCreator::create(['name' => 'مبدع', 'platform' => 'tiktok',
            'account_url' => 'https://t/@a', 'phone' => '0500000000', 'followers' => 5000]);

        $this->actingAs($this->admin())->post('/beta/admin/creator-pool/transfer', [
            'client_id' => $client->id, 'pool_ids' => [$p->id],
        ])->assertRedirect();

        TenantContext::bypass(true);
        $rec = PoolRecommendation::where('client_id', $client->id)->firstOrFail();
        TenantContext::reset();

        $this->assertSame('مبدع', $rec->name);
        $this->assertArrayNotHasKey('phone', $rec->getAttributes(), 'تسرّب الجوّال إلى توصية العميل');
        $json = json_encode($rec->getAttributes(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('0500000000', $json);
    }

    /** الأهمّ: التوصية تبقى وإن حُذفت القاعدة كلها (كِلّ سويتش). */
    public function test_recommendation_survives_pool_purge(): void
    {
        $client = $this->client();
        $p = PoolCreator::create(['name' => 'باقٍ', 'platform' => 'x', 'account_url' => 'https://x/1']);
        $this->actingAs($this->admin())->post('/beta/admin/creator-pool/transfer', [
            'client_id' => $client->id, 'pool_ids' => [$p->id],
        ]);

        PoolCreator::query()->delete(); // حذف القاعدة بالكامل

        TenantContext::bypass(true);
        $survived = PoolRecommendation::where('client_id', $client->id)->count();
        TenantContext::reset();
        $this->assertSame(1, $survived, 'ضاعت التوصية بحذف القاعدة');
    }

    public function test_transfer_does_not_duplicate_the_same_creator_to_the_same_client(): void
    {
        $client = $this->client();
        $p = PoolCreator::create(['name' => 'مبدع', 'platform' => 'tiktok', 'account_url' => 'https://t/@a']);
        $admin = $this->admin();
        $this->actingAs($admin)->post('/beta/admin/creator-pool/transfer', ['client_id' => $client->id, 'pool_ids' => [$p->id]]);
        $this->actingAs($admin)->post('/beta/admin/creator-pool/transfer', ['client_id' => $client->id, 'pool_ids' => [$p->id]]);

        TenantContext::bypass(true);
        $this->assertSame(1, PoolRecommendation::where('client_id', $client->id)->count(), 'كُرّرت التوصية');
        TenantContext::reset();
    }

    public function test_only_system_admin_can_transfer(): void
    {
        $client = $this->client();
        $p = PoolCreator::create(['name' => 'م', 'platform' => 'x', 'account_url' => 'https://x/2']);
        $plain = User::create(['name' => 'عادي', 'email' => 'q@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        $this->actingAs($plain)->post('/beta/admin/creator-pool/transfer', [
            'client_id' => $client->id, 'pool_ids' => [$p->id],
        ])->assertForbidden();
    }

}
