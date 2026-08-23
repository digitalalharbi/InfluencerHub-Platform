<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Models\PoolCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * استيعاب قاعدة المؤثرين (pool:ingest) — تطبيع + إزالة تكرار + إسقاط الحقول المحظورة + تكرار حتميّ.
 *
 * يستخدم بيانات اصطناعية فقط (لا PII حقيقية في المستودع).
 */
class CreatorPoolIngestTest extends TestCase
{
    use RefreshDatabase;

    private function writeFixture(array $rows): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/pool/test_raw.json', json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    public function test_ingest_normalizes_dedupes_and_drops_forbidden(): void
    {
        $this->writeFixture([
            // صفّ سليم مع معاملات تتبّع في الرابط + هاتف خام + متابعون K
            ['name' => 'مبدع أ', 'phone_raw' => '0501234567', 'platform_hint' => 'tiktok',
                'account_url_raw' => 'https://www.tiktok.com/@a?utm_source=x&si=1', 'followers_raw' => '12.7K',
                'categories_raw' => ['تغطية', 'قهوة'], 'shows_face_raw' => 'لا', 'creator_kind' => 'ugc',
                // حقول محظورة مُقحَمة عمدًا — يجب ألّا تُكتب
                'store' => 'وزنة', 'اسم الموظف' => 'ماجد', 'bank' => 'الراجحي'],
            // تكرار قويّ (نفس الرابط بعد التطبيع) — يُدمَج، ويملأ الغائب (المدينة)
            ['name' => 'مبدع أ', 'phone_raw' => null, 'platform_hint' => 'tiktok',
                'account_url_raw' => 'https://www.tiktok.com/@a/', 'city_raw' => 'الرياض'],
            // Notation علمي للهاتف
            ['name' => 'مبدع ب', 'phone_raw' => '5.32781865E8', 'platform_hint' => 'snapchat',
                'account_url_raw' => 'https://www.snapchat.com/add/b', 'followers_raw' => '2.1M'],
            // رابط غير صالح — يُستبعَد
            ['name' => 'مبدع ج', 'phone_raw' => '0555555555', 'platform_hint' => 'linkedin',
                'account_url_raw' => '(14) Some Name'],
        ]);

        $this->artisan('pool:ingest', ['--file' => 'private/pool/test_raw.json'])->assertSuccessful();

        // صفّان مقبولان (أ مدموج، ب)؛ ج مستبعَد (رابط غير صالح)
        $this->assertSame(2, PoolCreator::count());

        $a = PoolCreator::where('platform', 'tiktok')->first();
        $this->assertSame('966501234567', $a->phone);          // هاتف قانوني
        $this->assertSame(12_700, $a->followers);              // 12.7K
        $this->assertSame('الرياض', $a->city);                 // مُلئ من التكرار
        $this->assertSame('ugc', $a->source_type);
        // الرابط نُظِّف من معاملات التتبّع
        $this->assertSame('https://www.tiktok.com/@a', $a->account_url);

        $b = PoolCreator::where('platform', 'snapchat')->first();
        $this->assertSame('966532781865', $b->phone);          // Notation علمي
        $this->assertSame(2_100_000, $b->followers);

        // الحقول المحظورة لم تُكتب في أيّ عمود/قيمة
        $dump = json_encode(PoolCreator::all()->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('وزنة', $dump);
        $this->assertStringNotContainsString('ماجد', $dump);
        $this->assertStringNotContainsString('الراجحي', $dump);
    }

    public function test_ingest_is_idempotent(): void
    {
        $this->writeFixture([
            ['name' => 'مبدع', 'phone_raw' => '0501112222', 'platform_hint' => 'tiktok',
                'account_url_raw' => 'https://www.tiktok.com/@x', 'followers_raw' => '5000'],
        ]);

        $this->artisan('pool:ingest', ['--file' => 'private/pool/test_raw.json'])->assertSuccessful();
        $this->artisan('pool:ingest', ['--file' => 'private/pool/test_raw.json'])->assertSuccessful();

        $this->assertSame(1, PoolCreator::count()); // إعادة التشغيل لا تُكرّر
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->writeFixture([
            ['name' => 'مبدع', 'phone_raw' => '0501112222', 'platform_hint' => 'tiktok',
                'account_url_raw' => 'https://www.tiktok.com/@y'],
        ]);

        $this->artisan('pool:ingest', ['--file' => 'private/pool/test_raw.json', '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, PoolCreator::count());
    }
}
