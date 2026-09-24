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

    /** قفل البريد يتبع لغة المستقبِل: عربي «إنفلونسر هب» rtl · إنجليزي «InfluencerHub» ltr. */
    public function test_email_lockup_follows_recipient_locale(): void
    {
        $ar = \Illuminate\Support\Facades\Blade::render('<x-mail.layout locale="ar">م</x-mail.layout>');
        $this->assertStringContainsString('إنفلونسر هب', $ar);
        $this->assertStringContainsString('dir="rtl"', $ar);
        $this->assertStringContainsString('lang="ar"', $ar);

        $en = \Illuminate\Support\Facades\Blade::render('<x-mail.layout locale="en">x</x-mail.layout>');
        $this->assertStringContainsString('InfluencerHub', $en);
        $this->assertStringNotContainsString('إنفلونسر هب', $en);
        $this->assertStringContainsString('dir="ltr"', $en);
        $this->assertStringContainsString('lang="en"', $en);
    }

    /** شعار Blade يتبع اللغة: عربي «إنفلونسر هب» · إنجليزي «InfluencerHub». */
    public function test_blade_logo_is_locale_aware(): void
    {
        app()->setLocale('ar');
        $ar = \Illuminate\Support\Facades\Blade::render('<x-ih-logo :withWordmark="true" />');
        $this->assertStringContainsString('إنفلونسر', $ar);
        $this->assertStringContainsString('هب', $ar);
        $this->assertStringNotContainsString('InfluencerHub', $ar); // لا كلمة إنجليزية في السطح العربي

        app()->setLocale('en');
        $en = \Illuminate\Support\Facades\Blade::render('<x-ih-logo :withWordmark="true" />');
        $this->assertStringContainsString('Influencer', $en);
        $this->assertStringContainsString('Hub', $en);
        $this->assertStringNotContainsString('إنفلونسر', $en); // لا كلمة عربية في السطح الإنجليزي

        app()->setLocale('ar');
    }

    /** «هب/Hub» لا يُلوَّن سماويًّا أبدًا؛ النقطة وحدها سماوية. */
    public function test_blade_logo_hub_is_never_cyan(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render('<x-ih-logo :withWordmark="true" />');
        // النقطة السماوية موجودة (circle)، لكن لا نصّ ملوّن بالسماوي
        $this->assertStringContainsString('#22D3EE', $html);            // نقطة المنصّة
        $this->assertStringNotContainsString('color:#22D3EE', $html);   // لا نصّ سماوي
        $this->assertStringNotContainsString('color:#22d3ee', $html);
    }

    /** كل بوّابات الدخول (وكالة/مبدع/عميل/شريك) تعرض القفل الرسمي المحلّي، وشعارها يقود للرئيسية `/`. */
    public function test_all_portal_login_logos_are_localized_and_link_home(): void
    {
        foreach (['/login', '/creator/login', '/client/login', '/partner/login'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            // القفل الرسمي المحلّي (اللغة الافتراضية عربية) لا وسم إنجليزي مثبت
            $this->assertStringContainsString('إنفلونسر', $html, "الشعار العربي مفقود في {$path}");
            // شعار المصادقة يقود إلى الرئيسية التسويقية
            $this->assertStringContainsString('class="ih-auth__logo"', $html, "رابط شعار المصادقة مفقود في {$path}");
        }
    }

    /** شعار بوّابة كل دور يقود إلى رئيسية بوّابته (لا للجذر أعمى). */
    public function test_portal_chrome_logo_routes_to_its_home(): void
    {
        $map = [
            'client/layout' => '/client/dashboard',
            'creator/layout' => '/creator/dashboard',
            'partner/layout' => '/partner/dashboard',
        ];
        foreach ($map as $view => $home) {
            $src = file_get_contents(resource_path("views/{$view}.blade.php"));
            $this->assertStringContainsString('href="'.$home.'"', $src, "شعار {$view} لا يقود إلى {$home}");
        }
        // شعار مساحة الوكالة يقود إلى /app
        $this->assertStringContainsString('href="/app"', file_get_contents(resource_path('views/layouts/app.blade.php')));
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
