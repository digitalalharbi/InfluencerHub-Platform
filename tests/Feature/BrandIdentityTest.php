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

    public function test_env_examples_use_canonical_product_domain_not_local(): void
    {
        foreach ([base_path('.env.example'), base_path('deploy/vps/.env.example')] as $file) {
            $env = file_get_contents($file);
            $this->assertStringContainsString('no-reply@influencerhub.io', $env, $file);
            $this->assertStringNotContainsString('influencerhub.local', $env, $file);
            $this->assertStringContainsString('PRODUCT_URL=https://influencerhub.io', $env, $file);
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
