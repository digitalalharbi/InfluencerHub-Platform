<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Models\PoolCreator;
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
}
