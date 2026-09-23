<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الهوية البصرية الرسمية «الجسر (H)» — تُثبَّت في رأس المستند وملف PWA والأصول العامّة.
 * تمنع هذه الاختبارات عودة الهوية القديمة (Inter، أيقونة قديمة، لون سمة قديم) وغياب
 * صورة المعاينة الاجتماعية.
 */
class BrandIdentityTest extends TestCase
{
    use RefreshDatabase;

    /** رأس المستند يحمل الأيقونات الرسمية وصورة OG ولون السمة والخط الرسمي. */
    public function test_document_head_uses_official_brand(): void
    {
        $html = $this->get('/features')->assertOk()->getContent();

        // أيقونات + PWA + معاينة اجتماعية رسمية
        $this->assertStringContainsString('/favicon.svg', $html);
        $this->assertStringContainsString('/apple-touch-icon.png', $html);
        $this->assertStringContainsString('/site.webmanifest', $html);
        $this->assertStringContainsString('/og-image-1200x630.png', $html);
        $this->assertStringContainsString('summary_large_image', $html);
        // لون السمة الرسمي (إنديغو) لا القديم
        $this->assertStringContainsString('name="theme-color" content="#5B45E0"', $html);
        // خط لاتيني رسمي (IBM Plex Sans) لا Inter
        $this->assertStringContainsString('IBM+Plex+Sans', $html);
        $this->assertStringNotContainsString('family=Inter', $html);
        // لا أثر للأيقونة القديمة
        $this->assertStringNotContainsString('/icons/ih-icon.svg', $html);
    }

    /** ملف PWA الرسمي يحمل اسم المنتج ولون السمة الصحيح (يُخدَم كملفّ ثابت من public). */
    public function test_official_manifest_is_valid(): void
    {
        $json = json_decode((string) file_get_contents(public_path('site.webmanifest')), true);
        $this->assertSame('InfluencerHub', $json['name'] ?? null);
        $this->assertSame('#5B45E0', $json['theme_color'] ?? null);
        $this->assertNotEmpty($json['icons'] ?? []);
    }

    /** الأصول الرسمية موجودة فعلًا في public (لا رابط ميت). */
    public function test_official_assets_exist(): void
    {
        foreach (['favicon.svg', 'apple-touch-icon.png', 'og-image-1200x630.png', 'icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'site.webmanifest'] as $asset) {
            $this->assertFileExists(public_path($asset), "أصل الهوية المفقود: {$asset}");
        }
    }

    /** غلاف البريد يحمل بلاطة الهوية الرسمية ولون إنديغو الرسمي لا القديم. */
    public function test_email_shell_uses_official_brand(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render('<x-mail.layout>محتوى</x-mail.layout>');
        // بلاطة الهوية كـPNG رسمي (توافق عملاء البريد)
        $this->assertStringContainsString('/icon-192.png', $html);
        // لون الهوية الرسمي في الروابط، لا القديم
        $this->assertStringContainsString('#5B45E0', $html);
        $this->assertStringNotContainsString('#6252e5', $html);
    }

    /** صفحات الخطأ تستخدم البلاطة الرسمية ولون الهوية والأيقونة الرسمية. */
    public function test_error_shell_uses_official_brand(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render('<x-error-shell code="404" title="غير موجود" message="تعذّر العثور" />');
        $this->assertStringContainsString('rx="23.33"', $html);       // بلاطة «الجسر (H)»
        $this->assertStringContainsString('#5B45E0', $html);          // إنديغو رسمي
        $this->assertStringContainsString('/favicon.svg', $html);      // أيقونة رسمية
        $this->assertStringNotContainsString('#6d5df6', $html);        // لا لون قديم منحرف
        $this->assertStringNotContainsString('/icons/ih-icon.svg', $html);
    }
}
