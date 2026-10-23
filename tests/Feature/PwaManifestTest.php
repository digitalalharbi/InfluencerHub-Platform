<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * بوّابة PWA — مانيفست كنسي واحد، بلا القديم، وService Worker مسجّل فعلًا.
 * هوية IDENTITY-02: theme/background محايد (#F5F6F3)، لا بنفسجي/شارتروز.
 */
class PwaManifestTest extends TestCase
{
    public function test_single_canonical_manifest_only(): void
    {
        $this->assertFileExists(public_path('site.webmanifest'), 'المانيفست الكنسي مفقود');
        $this->assertFileDoesNotExist(public_path('manifest.webmanifest'), 'المانيفست القديم المكرّر ما زال موجودًا');
    }

    public function test_canonical_manifest_is_valid_and_complete(): void
    {
        $m = json_decode((string) file_get_contents(public_path('site.webmanifest')), true);
        $this->assertIsArray($m, 'مانيفست غير صالح JSON');
        $this->assertSame('/app', $m['id'] ?? null);
        $this->assertSame('InfluencerHub', $m['name'] ?? null);
        $this->assertNotEmpty($m['short_name'] ?? null);
        $this->assertNotEmpty($m['description'] ?? null);
        $this->assertSame('/app', $m['start_url'] ?? null);
        $this->assertSame('/', $m['scope'] ?? null);
        $this->assertSame('standalone', $m['display'] ?? null);
        $this->assertNotEmpty($m['dir'] ?? null);
        $this->assertNotEmpty($m['lang'] ?? null);
        // هوية IDENTITY-02 المحايدة، لا البنفسجي القديم
        $this->assertSame('#F5F6F3', $m['theme_color'] ?? null);
        $this->assertSame('#F5F6F3', $m['background_color'] ?? null);
        $this->assertNotContains($m['theme_color'] ?? '', ['#6252E5', '#5B45E0'], 'theme_color يحمل هوية بنفسجية قديمة');

        // أيقونات: 192 + 512 + maskable
        $srcs = array_map(fn ($i) => $i['src'] ?? '', $m['icons'] ?? []);
        $purposes = array_map(fn ($i) => $i['purpose'] ?? '', $m['icons'] ?? []);
        $this->assertContains('/icon-192.png', $srcs);
        $this->assertContains('/icon-512.png', $srcs);
        $this->assertcontains('maskable', $purposes, 'أيقونة maskable مفقودة');
    }

    public function test_pwa_icon_assets_exist(): void
    {
        foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'favicon.svg', 'offline.html'] as $asset) {
            $this->assertFileExists(public_path($asset), "أصل PWA مفقود: {$asset}");
        }
    }

    public function test_service_worker_is_canonical_and_versioned(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("VERSION = 'ih-v2'", $sw, 'نسخة SW لم تُرفَع');
        $this->assertStringContainsString('/site.webmanifest', $sw, 'SW لا يُسبق تخزين المانيفست الكنسي');
        $this->assertStringNotContainsString('/manifest.webmanifest', $sw, 'SW ما زال يشير للمانيفست القديم');
        // الأمان: تنقّل network-first وبديل offline
        $this->assertStringContainsString('offline.html', $sw);
    }

    public function test_service_worker_is_registered_in_app_bootstrap(): void
    {
        $pwa = (string) file_get_contents(base_path('resources/js/lib/pwa.ts'));
        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js')", $pwa, 'كود تسجيل SW مفقود');

        $inertia = (string) file_get_contents(base_path('resources/js/inertia.tsx'));
        $this->assertStringContainsString('registerServiceWorker', $inertia, 'تطبيق Inertia لا يسجّل SW');
    }

    public function test_app_head_declares_pwa_and_neutral_theme(): void
    {
        $html = $this->get('/features')->assertOk()->getContent();
        $this->assertStringContainsString('rel="manifest" href="/site.webmanifest"', $html);
        $this->assertStringContainsString('name="theme-color" content="#F5F6F3"', $html);
        $this->assertStringContainsString('name="apple-mobile-web-app-capable"', $html);
        $this->assertStringContainsString('name="apple-mobile-web-app-title" content="InfluencerHub"', $html);
        $this->assertStringNotContainsString('manifest.webmanifest', $html);
        $this->assertStringNotContainsString('#5B45E0', $html);
        $this->assertStringNotContainsString('#C7ED63', $html);
    }
}
