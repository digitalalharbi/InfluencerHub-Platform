<?php

namespace Tests\Unit;

use App\Domain\AdminPool\Support\CreatorNormalizer as N;
use PHPUnit\Framework\TestCase;

/** تطبيع بيانات المبدعين المستوردة — صيغ فعلية من ملفات الاستيراد الحقيقية. */
class CreatorNormalizerTest extends TestCase
{
    /** الهاتف يبقى نصًّا ويُوحَّد إلى 9665XXXXXXXX، والمشوَّه يُترَك null. */
    public function test_phone_saudi_normalization(): void
    {
        $this->assertSame('966501234567', N::phone('0501234567'));
        $this->assertSame('966501234567', N::phone('501234567'));
        $this->assertSame('966501234567', N::phone('966501234567'));
        $this->assertSame('966501234567', N::phone('+966501234567'));
        $this->assertSame('966501234567', N::phone('00966501234567'));
        $this->assertSame('966501234567', N::phone('05 01 23 45 67'));
        $this->assertSame('966501234567', N::phone('٠٥٠١٢٣٤٥٦٧')); // أرقام عربية
    }

    /** Notation علمي من Excel يُستعاد بلا فقدان دلالة الأرقام. */
    public function test_phone_scientific_notation_recovery(): void
    {
        // 5.32781865E8 = 532781865 → جوّال سعودي صالح
        $this->assertSame('966532781865', N::phone('5.32781865E8'));
        $this->assertSame('966501480400', N::phone('5.014804E8')); // 501480400
    }

    public function test_phone_rejects_invalid_without_guessing(): void
    {
        $this->assertNull(N::phone('0.0'));
        $this->assertNull(N::phone('123'));
        $this->assertNull(N::phone(''));
        $this->assertNull(N::phone(null));
        $this->assertNull(N::phone('4991234567')); // لا يبدأ بـ5
    }

    public function test_phone_display(): void
    {
        $this->assertSame('+966 50 123 4567', N::phoneDisplay('966501234567'));
        $this->assertSame(null, N::phoneDisplay(null));
    }

    /** متابعون/لايكات من صيغ K/M/الف + أرقام عربية + عشرية. */
    public function test_count_notation(): void
    {
        $this->assertSame(2_100_000, N::count('2.1M'));
        $this->assertSame(113_000, N::count('113K'));
        $this->assertSame(728_000, N::count('728K'));
        $this->assertSame(10_300, N::count('10.3الف'));
        $this->assertSame(182_000, N::count('١٨٢الف'));   // عربي + الف
        $this->assertSame(307_700, N::count('٣٠٧.٧الف'));
        $this->assertSame(3_735, N::count('3735.0'));
        $this->assertSame(44, N::count('44.0'));
        $this->assertSame(0, N::count('0.0'));
        $this->assertSame(400_000, N::count('400K'));
    }

    public function test_count_ambiguous_is_null(): void
    {
        $this->assertNull(N::count(''));
        $this->assertNull(N::count(null));
        $this->assertNull(N::count('Followers'));
        $this->assertNull(N::count('غير معروف'));
    }

    /** المنصّة تُكتشَف من الرابط لا من اسم الورقة. */
    public function test_platform_detection_from_url(): void
    {
        $this->assertSame('tiktok', N::platform('https://www.tiktok.com/@x'));
        $this->assertSame('snapchat', N::platform('https://www.snapchat.com/add/y'));
        $this->assertSame('x', N::platform('https://x.com/y'));
        $this->assertSame('x', N::platform('https://twitter.com/y')); // twitter → x
        $this->assertSame('linkedin', N::platform('https://www.linkedin.com/in/z'));
        $this->assertSame('instagram', N::platform('https://instagram.com/z'));
    }

    public function test_platform_falls_back_to_hint_when_url_unhelpful(): void
    {
        $this->assertSame('linkedin', N::platform('(14) Muath Almosallam', 'linkedin'));
        $this->assertSame('x', N::platform('(1) نرنر (@Nkt_767)', 'x'));
        $this->assertNull(N::platform('random text', 'ugc')); // ugc ليس منصّة اجتماعية
        $this->assertNull(N::platform(null, null));
    }

    /** الرابط يُنظَّف من معاملات التتبّع ويُستخرَج من نصّ مبعثر أو يُترَك null. */
    public function test_account_url_cleaning(): void
    {
        $this->assertSame('https://www.tiktok.com/@x', N::accountUrl('https://www.tiktok.com/@x?utm_source=abc&si=1'));
        $this->assertSame('https://www.tiktok.com/@x', N::accountUrl('www.tiktok.com/@x'));
        $this->assertSame('https://snapchat.com/add/y', N::accountUrl('https://snapchat.com/add/y/'));
        $this->assertNull(N::accountUrl('(14) Muath Almosallam')); // بلا رابط
        $this->assertNull(N::accountUrl(''));
    }

    public function test_identity_key_for_dedup(): void
    {
        $this->assertSame('tiktok|https://www.tiktok.com/@x', N::identityKey('tiktok', 'https://www.tiktok.com/@x'));
        $this->assertSame('tiktok|https://www.tiktok.com/@x', N::identityKey('TikTok', 'https://www.tiktok.com/@x/'));
        $this->assertNull(N::identityKey(null, 'https://x'));
        $this->assertNull(N::identityKey('tiktok', null));
    }

    public function test_scalar_fields(): void
    {
        $this->assertSame('male', N::gender('ذكر'));
        $this->assertSame('female', N::gender('انثى'));
        $this->assertNull(N::gender('0.0'));

        $this->assertTrue(N::showsFace('نعم'));
        $this->assertFalse(N::showsFace('لا'));
        $this->assertNull(N::showsFace('ربما'));

        $this->assertSame('A', N::tier('A'));
        $this->assertSame('C', N::tier('c'));
        $this->assertNull(N::tier('روتس'));

        $this->assertSame('ممتاز', N::rating('ممتاز'));
        $this->assertSame('سيئ', N::rating('سئ'));
        $this->assertNull(N::rating('0.0'));
    }

    public function test_categories_clean_dedupe(): void
    {
        $this->assertSame(['رياضة', 'أسلوب حياة', 'يوميات'], N::categories(['رياضة', 'اسلوب حياة', 'يوميات ', 'رياضة', '0', '', null]));
        $this->assertSame([], N::categories(['0.0', '', null]));
    }
}
