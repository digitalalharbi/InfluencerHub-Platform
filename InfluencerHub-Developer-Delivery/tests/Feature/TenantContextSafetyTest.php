<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * سلامة سياق المستأجر.
 *
 * النمط الذي تكرّر ثماني مرّات: كود يضبط السياق مؤقّتًا ثم «يُعيده» بـ`reset()`
 * — و`reset()` لا يُعيد شيئًا بل يُفرغ كل شيء. فالاستعلام التالي يعود فارغًا
 * **بصمت** ويُقرأ «لا سجلّ» أو «لا مستقبِل»: تسقط إشعارات، ويُتخطّى حارس تكرار
 * كأن لا تعارض، ويُردّ مستخدم 403 لأن مؤسسته مُسحت.
 *
 * هذه الاختبارات تحرس الأدوات التي تمنع عودة النمط.
 */
class TenantContextSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    // ===== العيب نفسه، موثّقًا =====

    /** `set($tenantId)` وحده يمسح المؤسسة — سبب أعطال 403 المتكرّرة. */
    public function test_set_with_only_a_tenant_wipes_the_organization(): void
    {
        TenantContext::set(7, 70);
        $this->assertSame(70, TenantContext::organizationId());

        TenantContext::set(7);
        $this->assertNull(TenantContext::organizationId(),
            'سلوك موثَّق: set يكتب الحقول الثلاثة — لذلك تُفضَّل withTenant');
    }

    // ===== withTenant =====

    /** يستعيد السياق السابق بعد الفراغ. */
    public function test_with_tenant_restores_the_previous_context(): void
    {
        TenantContext::set(1, 10, 100);

        $seen = TenantContext::withTenant(2, fn () => TenantContext::tenantId());

        $this->assertSame(2, $seen, 'لم يُطبَّق السياق الجديد داخل العمل');
        $this->assertSame(1, TenantContext::tenantId());
        $this->assertSame(10, TenantContext::organizationId());
        $this->assertSame(100, TenantContext::workspaceId());
    }

    /** ويستعيده حتّى عند الاستثناء — وهو الفارق عن `try/finally` اليدوي المنسيّ. */
    public function test_with_tenant_restores_even_when_the_work_throws(): void
    {
        TenantContext::set(1, 10);

        try {
            TenantContext::withTenant(2, function () { throw new \RuntimeException('فشل'); });
            $this->fail('لم يُعَد رمي الاستثناء');
        } catch (\RuntimeException) {
            // متوقّع
        }

        $this->assertSame(1, TenantContext::tenantId(), 'ضاع السياق بعد استثناء');
        $this->assertSame(10, TenantContext::organizationId());
    }

    /** إعادة تأكيد المستأجر نفسه لا تُسقط المؤسسة القائمة. */
    public function test_reasserting_the_same_tenant_keeps_the_organization(): void
    {
        TenantContext::set(5, 50, 500);

        TenantContext::withTenant(5, function () {
            $this->assertSame(50, TenantContext::organizationId(),
                'أُسقطت المؤسسة عند إعادة تأكيد المستأجر نفسه — وهو سبب 403');
            $this->assertSame(500, TenantContext::workspaceId());
        });
    }

    /** والتداخل يعود طبقةً طبقة. */
    public function test_nested_scopes_unwind_in_order(): void
    {
        TenantContext::set(1, 10);

        TenantContext::withTenant(2, function () {
            $this->assertSame(2, TenantContext::tenantId());
            TenantContext::withTenant(3, fn () => $this->assertSame(3, TenantContext::tenantId()));
            $this->assertSame(2, TenantContext::tenantId(), 'لم يعد للطبقة الوسطى');
        });

        $this->assertSame(1, TenantContext::tenantId());
        $this->assertSame(10, TenantContext::organizationId());
    }

    // ===== withBypass =====

    /** التجاوز يُغلق بعد العمل — تركه مفتوحًا يُبطل العزل صامتًا. */
    public function test_with_bypass_closes_afterwards(): void
    {
        TenantContext::set(1, 10);

        $inside = TenantContext::withBypass(fn () => TenantContext::bypassing());

        $this->assertTrue($inside);
        $this->assertFalse(TenantContext::bypassing(), 'بقي التجاوز مفتوحًا بعد العمل');
        $this->assertSame(1, TenantContext::tenantId(), 'التجاوز أضاع سياق المستأجر');
    }

    public function test_with_bypass_closes_even_when_the_work_throws(): void
    {
        TenantContext::set(1, 10);

        try {
            TenantContext::withBypass(function () { throw new \RuntimeException('فشل'); });
        } catch (\RuntimeException) {
            // متوقّع
        }

        $this->assertFalse(TenantContext::bypassing(), 'بقي التجاوز مفتوحًا بعد استثناء');
        $this->assertSame(1, TenantContext::tenantId());
    }

    /** ولا يُبقي تجاوزًا لم يكن مفتوحًا أصلًا، ولا يُغلق تجاوزًا كان مفتوحًا. */
    public function test_bypass_state_is_restored_not_forced(): void
    {
        TenantContext::bypass(true);
        TenantContext::withBypass(fn () => null);
        $this->assertTrue(TenantContext::bypassing(), 'أُغلق تجاوز كان مفتوحًا قبل العمل');

        TenantContext::reset();
        TenantContext::withBypass(fn () => null);
        $this->assertFalse(TenantContext::bypassing());
    }

    /** ولقطة/استعادة تحفظ التجاوز أيضًا — لا المستأجر وحده. */
    public function test_snapshot_captures_bypass_too(): void
    {
        TenantContext::set(3, 30);
        TenantContext::bypass(true);
        $snap = TenantContext::snapshot();

        TenantContext::reset();
        $this->assertFalse(TenantContext::bypassing());

        TenantContext::restore($snap);
        $this->assertSame(3, TenantContext::tenantId());
        $this->assertSame(30, TenantContext::organizationId());
        $this->assertTrue(TenantContext::bypassing());
    }

    // ===== الخروج من النطاق بكل طرقه =====

    /**
     * `abort()` يرمي HttpException — وهي الطريقة الأشيع للخروج من نطاق مضبوط
     * في كود HTTP. لو لم يُستعاد السياق هنا لتسرّب مستأجر إلى معالج الخطأ
     * وإلى ما يبنيه من ردّ.
     */
    public function test_context_is_restored_after_abort(): void
    {
        TenantContext::set(3, 30, 300);

        try {
            TenantContext::withTenant(9, fn () => abort(404));
            $this->fail('كان يجب أن يرمي abort');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            // متوقّع
        }

        $this->assertSame(3, TenantContext::tenantId());
        $this->assertSame(30, TenantContext::organizationId());
        $this->assertSame(300, TenantContext::workspaceId());
    }

    /** الخروج بـ`return` من داخل النطاق يستعيد أيضًا — لا يعتمد على بلوغ آخر سطر. */
    public function test_context_is_restored_after_an_early_return(): void
    {
        TenantContext::set(3, 30);

        $result = TenantContext::withTenant(9, function () {
            if (true) {
                return 'خرجتُ مبكّرًا';
            }

            return 'لن يُبلَغ';
        });

        $this->assertSame('خرجتُ مبكّرًا', $result);
        $this->assertSame(3, TenantContext::tenantId());
        $this->assertSame(30, TenantContext::organizationId());
    }

    /**
     * استثناء من **الطبقة الداخلية** يعبر الطبقتين: كلٌّ تستعيد لقطتها، فيعود
     * السياق إلى ما قبل الأولى لا إلى حالة وسطى.
     */
    public function test_nested_scopes_unwind_fully_when_the_inner_one_throws(): void
    {
        TenantContext::set(1, 10, 100);

        try {
            TenantContext::withTenant(2, function () {
                TenantContext::withTenant(3, function () {
                    throw new \RuntimeException('من العمق');
                });
            });
            $this->fail('كان يجب أن يصعد الاستثناء');
        } catch (\RuntimeException $e) {
            $this->assertSame('من العمق', $e->getMessage());
        }

        $this->assertSame(1, TenantContext::tenantId());
        $this->assertSame(10, TenantContext::organizationId());
        $this->assertSame(100, TenantContext::workspaceId());
        $this->assertFalse(TenantContext::bypassing());
    }

    /** القيمة تعبر النطاق — وهذا ما يُغني عن تسريب متغيّر من داخل الكتلة. */
    public function test_with_tenant_returns_the_value_of_its_work(): void
    {
        $value = TenantContext::withTenant(5, fn () => ['tenant' => TenantContext::tenantId(), 'n' => 42]);

        $this->assertSame(['tenant' => 5, 'n' => 42], $value);
        $this->assertNull(TenantContext::tenantId());
    }

    /** ورشة العمل تُحفظ كالمؤسسة عند إعادة تأكيد المستأجر نفسه. */
    public function test_reasserting_the_same_tenant_keeps_the_workspace(): void
    {
        TenantContext::set(4, 40, 400);

        TenantContext::withTenant(4, function () {
            $this->assertSame(40, TenantContext::organizationId());
            $this->assertSame(400, TenantContext::workspaceId());
        });

        $this->assertSame(400, TenantContext::workspaceId());
    }

    /**
     * أمران متتابعان في **عملية واحدة** (كما في `artisan` أو المجدوِل): الثاني
     * يجب ألّا يرث سياق الأوّل. العملية طويلة العمر هي ما يجعل التسريب مؤذيًا.
     */
    public function test_sequential_commands_in_one_process_do_not_inherit_context(): void
    {
        $seen = [];

        $command = function (int $tenantId) use (&$seen) {
            $seen[] = TenantContext::tenantId();   // ما ورثه هذا الأمر
            TenantContext::withTenant($tenantId, fn () => null);
        };

        $command(11);
        $command(22);
        $command(33);

        $this->assertSame([null, null, null], $seen,
            'كل أمر بدأ بلا سياق — لا وراثة من سابقه في العملية نفسها');
        $this->assertNull(TenantContext::tenantId());
    }
}
