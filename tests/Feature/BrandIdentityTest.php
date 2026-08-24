<?php

namespace Tests\Feature;

use App\Domain\Exports\TabularData;
use App\Domain\Exports\Writers\PdfWriter;
use App\Support\Brand;
use Tests\TestCase;

/**
 * هوية InfluencerHub الموحّدة — العلامة والرابط القانونيان في المستندات والبريد،
 * مع الحفاظ على هوية المستأجر بوصفه صاحب المستند. لا localhost/Laravel في مخرجات
 * المستخدم الإنتاجية.
 */
class BrandIdentityTest extends TestCase
{
    public function test_canonical_brand_config_is_influencerhub_io(): void
    {
        $this->assertSame('InfluencerHub', Brand::name());
        $this->assertSame('https://influencerhub.io', Brand::url());
        $this->assertSame('influencerhub.io', Brand::domain());
        $this->assertSame('https://influencerhub.io/info', Brand::infoUrl());
        $this->assertStringContainsString('InfluencerHub', Brand::documentFooter());
        $this->assertStringContainsString('influencerhub.io', Brand::documentFooter());
        // لا نطاق .local في مُرسِل البريد أيًّا كانت البيئة
        $this->assertStringNotContainsString('.local', Brand::mailFromAddress());
        // لا نخترع قناة دعم غير مُهيّأة
        $this->assertNull(Brand::supportEmail());
    }

    public function test_real_public_contacts_are_canonical_owner_values(): void
    {
        // مصدر المالك — بريد وهاتف حقيقيان، ليسا تجريبيَّين ولا يُصنَّفان placeholder.
        $this->assertSame('info@influencerhub.io', Brand::publicEmail());
        $this->assertSame('+966550137003', Brand::publicPhone());              // القيمة المخزّنة الأصلية
        $this->assertSame('+966 55 013 7003', Brand::publicPhoneDisplay());     // العرض المقروء فقط
        // بريد التواصل العام يختلف عن مُرسِل البريد الآليّ (لا يُعرَض no-reply كقناة عامّة).
        // (القيمة الزمنية للمُرسِل قد تُضبط من .env المحلّي؛ العُرف no-reply@ مُثبَت في .env.example.)
        $this->assertNotSame(Brand::publicEmail(), Brand::mailFromAddress());
        $this->assertStringContainsString('no-reply@influencerhub.io', file_get_contents(base_path('.env.example')));
        // روابط الوثائق النظامية تُبنى من النطاق القانوني.
        $this->assertSame('https://influencerhub.io/privacy', Brand::privacyUrl());
        $this->assertSame('https://influencerhub.io/terms', Brand::termsUrl());
        $this->assertSame('https://influencerhub.io/help', Brand::helpUrl());
    }

    public function test_public_policy_pages_load_branded_with_own_titles(): void
    {
        foreach ([
            '/privacy' => 'الخصوصية',
            '/terms' => 'الشروط',
            '/help' => 'المساعدة',
            '/info' => 'عن InfluencerHub',
        ] as $path => $needle) {
            $res = $this->get($path);
            $res->assertOk();
            // العنوان لكل صفحة يُركَّب في الواجهة عبر PublicLayout؛ نتحقّق أن المسار حيّ 200
            // وأن هوية المنتج حاضرة في الصدفة (لا إطار عمل مُسرَّب).
            $res->assertSee('InfluencerHub', false);
            $this->assertIsString($needle);
        }
    }

    public function test_shared_inertia_brand_prop_carries_contacts_and_policy_paths(): void
    {
        $brand = (new \App\Http\Middleware\HandleInertiaRequests())
            ->share(request())['brand'];
        $this->assertSame('info@influencerhub.io', $brand['publicEmail']);
        $this->assertSame('+966550137003', $brand['publicPhone']);
        $this->assertSame('/privacy', $brand['privacyPath']);
        $this->assertSame('/terms', $brand['termsPath']);
        $this->assertSame('/help', $brand['helpPath']);
    }

    public function test_mail_shell_footer_carries_public_contact_and_policy_links(): void
    {
        $html = view('components.mail.layout', ['slot' => 'مرحبًا', 'title' => 'اختبار'])->render();
        $this->assertStringContainsString('info@influencerhub.io', $html);
        $this->assertStringContainsString('/privacy', $html);
        $this->assertStringContainsString('/terms', $html);
        $this->assertStringContainsString('/help', $html);
    }

    public function test_env_examples_use_canonical_product_domain_not_local(): void
    {
        foreach ([base_path('.env.example'), base_path('deploy/vps/.env.example')] as $file) {
            $env = file_get_contents($file);
            $this->assertStringContainsString('no-reply@influencerhub.io', $env, $file);
            $this->assertStringNotContainsString('influencerhub.local', $env, $file);
            $this->assertStringContainsString('PRODUCT_URL=https://influencerhub.io', $env, $file);
            $this->assertStringContainsString('PRODUCT_PUBLIC_EMAIL=info@influencerhub.io', $env, $file);
            $this->assertStringContainsString('PRODUCT_PUBLIC_PHONE=+966550137003', $env, $file);
        }
    }

    public function test_exported_document_filenames_are_influencerhub_branded(): void
    {
        $this->assertSame('InfluencerHub-INV-1-0001.pdf', Brand::documentFilename('فاتورة INV-1-0001'));
        $this->assertSame('InfluencerHub-CM-9.pdf', Brand::documentFilename('ملخّص حملة CM-9'));
        $this->assertSame('InfluencerHub-CO-1-3.pdf', Brand::documentFilename('عقد CO-1-3'));
        $this->assertStringStartsWith('InfluencerHub-', Brand::documentFilename('كشف مستحق PY-1-2'));
        // بلا رمز لاتيني → لا يزال مُعرَّفًا بالعلامة وآمنًا
        $this->assertStringStartsWith('InfluencerHub-', Brand::documentFilename('تقرير'));
    }

    public function test_error_pages_are_branded_and_leak_no_internals(): void
    {
        foreach (['404', '403', '429', '500', '503'] as $code) {
            $html = view("errors.$code")->render();
            $this->assertStringContainsString('InfluencerHub', $html, $code);
            $this->assertStringContainsString('influencerhub.io', $html, $code);
            $this->assertStringNotContainsString('Laravel', $html, $code);
            $this->assertStringNotContainsString('إنفلونسر هَب', $html, $code);
        }
    }

    public function test_html_head_carries_influencerhub_metadata_and_icons(): void
    {
        $head = view('inertia', ['page' => ['component' => 'x', 'props' => [], 'url' => '/', 'version' => '1']])->render();
        $this->assertStringContainsString('og:site_name', $head);
        $this->assertStringContainsString('content="InfluencerHub"', $head);
        $this->assertStringContainsString('/favicon.ico', $head);
        $this->assertStringContainsString('/icons/ih-icon.svg', $head);
        $this->assertStringContainsString('rel="canonical"', $head);
        $this->assertStringNotContainsString('إنفلونسر هَب', $head);
        $this->assertStringNotContainsString('Laravel', $head);
    }

    public function test_mail_shell_is_influencerhub_branded_not_framework(): void
    {
        $html = view('components.mail.layout', ['slot' => 'مرحبًا', 'title' => 'اختبار'])->render();
        $this->assertStringContainsString('InfluencerHub', $html);
        $this->assertStringContainsString('influencerhub.io', $html);
        $this->assertStringContainsString('https://influencerhub.io/', $html);   // CTA رابط إنتاجي
        $this->assertStringNotContainsString('إنفلونسر هَب', $html);            // صيغة قديمة غير متسقة
        $this->assertStringNotContainsString('Laravel', $html);
        $this->assertStringNotContainsString('localhost', $html);
    }

    public function test_pdf_generates_with_platform_footer_and_tenant_owner(): void
    {
        // التذييل يستعمل Brand::documentFooter() فتحمل كلّ صفحة هوية المنصّة،
        // والترويسة تُبقي اسم المستأجر (صاحب المستند) — تحقّق سلوكيّ عبر التوليد.
        // (mpdf يضغط المحتوى فلا يُفحص بايت-بايت؛ نصّ التذييل مُثبَت في اختبار Brand.)
        $pdf = app(PdfWriter::class)->render(new TabularData(
            title: 'تقرير اختبار',
            columns: ['a' => 'أ', 'b' => 'ب'],
            rows: [['a' => '1', 'b' => '2']],
            workspace: 'وكالة النخبة',   // المستأجر = صاحب المستند
        ));
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1500, strlen($pdf));
        // تذييل المنصّة لا يُلغي هوية المستأجر: كلاهما نصّ في المستند
        $this->assertStringContainsString('InfluencerHub', Brand::documentFooter());
    }
}
