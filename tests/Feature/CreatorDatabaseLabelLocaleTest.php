<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Models\PoolCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * تسميات قاعدة المؤثرين المبنيّة على الخادم تتبع لغة الطلب دون مسّ المفاتيح:
 * أسماء المنصّات من PlatformRegistry، وتصنيف المبدع والملاحظات من مجموعة الترجمة.
 * العربية (الافتراضيّة) تبقى مطابقة حرفيًّا للثوابت السابقة.
 */
class CreatorDatabaseLabelLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        App::setLocale('ar');
        parent::tearDown();
    }

    private function pool(string $platform, string $sourceType): PoolCreator
    {
        return PoolCreator::create(['name' => 'نجم', 'platform' => $platform, 'source_type' => $sourceType]);
    }

    public function test_arabic_labels_match_previous_constants(): void
    {
        App::setLocale('ar');
        $celeb = $this->pool('snapchat', 'celebrity')->toSharedArray();
        $ugc = $this->pool('tiktok', 'ugc')->toSharedArray();

        $this->assertSame('سناب شات', $celeb['platformLabel']);
        $this->assertSame('مؤثّر', $celeb['creatorTypeLabel']);
        $this->assertSame('تيك توك', $ugc['platformLabel']);
        $this->assertSame('صانع UGC', $ugc['creatorTypeLabel']);
        $this->assertSame('سعر مرجعي مسجّل — غير مضمون؛ يُتفاوَض عليه مع المبدع', $celeb['referenceRateNote']);
        $this->assertSame('بيانات مسجّلة', $celeb['dataFreshness']);
    }

    public function test_english_labels_are_translated(): void
    {
        App::setLocale('en');
        $celeb = $this->pool('snapchat', 'celebrity')->toSharedArray();
        $ugc = $this->pool('instagram', 'ugc')->toSharedArray();

        $this->assertSame('Snapchat', $celeb['platformLabel']);
        $this->assertSame('Influencer', $celeb['creatorTypeLabel']);
        $this->assertSame('Instagram', $ugc['platformLabel']);
        $this->assertSame('UGC Creator', $ugc['creatorTypeLabel']);
        $this->assertSame('Recorded data', $celeb['dataFreshness']);
        $this->assertNotSame('creator_database.reference_rate_note', $celeb['referenceRateNote']);
    }
}
