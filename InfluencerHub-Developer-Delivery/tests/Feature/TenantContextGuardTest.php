<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * الحارس نفسه تحت الاختبار.
 *
 * سكربت يفحص الكود ولا أحد يفحصه هو نقطة عمياء: يكفي أن يُكسر تعبيره النمطي
 * أو يُبتلع خطؤه ليصير أخضر دائمًا — فيبدو المستودع محروسًا وهو مكشوف. وقد وقع
 * ذلك فعلًا: ببلوغ الصفر صار `grep` يخرج بـ1، و`set -e` يقتل الحارس **عند
 * النجاح** بلا رسالة.
 *
 * ولا يوجد CI في هذا المستودع، فالاختبارات هي مكان الإنفاذ الوحيد الذي يُشغَّل
 * فعلًا. إن أُضيف CI لاحقًا فليستدعِ السكربت مباشرةً أيضًا.
 */
class TenantContextGuardTest extends TestCase
{
    private function runGuard(): array
    {
        $script = base_path('scripts/check-tenant-context-safety.sh');
        exec("bash {$script} 2>&1", $out, $code);

        return [$code, implode("\n", $out)];
    }

    public function test_the_guard_passes_on_the_current_tree(): void
    {
        [$code, $out] = $this->runGuard();

        $this->assertSame(0, $code, "الحارس أحمر على الشجرة الحالية:\n{$out}");
        $this->assertStringContainsString('صفر استدعاء يدوي', $out);
    }

    /**
     * @dataProvider layers
     */
    public function test_the_guard_rejects_a_manual_call_in_every_layer(string $dir): void
    {
        $probe = base_path($dir) . '/__GuardProbe.php';
        @mkdir(dirname($probe), 0755, true);
        file_put_contents($probe, "<?php\n\\App\\Domain\\Tenancy\\Support\\TenantContext::reset();\n");

        try {
            [$code, $out] = $this->runGuard();
        } finally {
            @unlink($probe);
        }

        $this->assertSame(1, $code, "الحارس لم يرفض استدعاءً يدويًّا في {$dir}");
        $this->assertStringContainsString('__GuardProbe.php', $out);
    }

    public static function layers(): array
    {
        return [
            'النطاق' => ['app/Domain/CRM/Services'],
            'المتحكّمات' => ['app/Http/Controllers'],
            'المهامّ' => ['app/Jobs'],
            'المستمعون' => ['app/Listeners'],
            'الأوامر' => ['app/Console/Commands'],
            'المُساعِدات' => ['app/Support'],
            'الوسائط' => ['app/Http/Middleware'],
        ];
    }

    /** الاستثناءات مذكورة بالاسم — لا نمط واسع يبتلع ما لم يُقصد. */
    public function test_the_exemption_list_stays_small_and_named(): void
    {
        $script = file_get_contents(base_path('scripts/check-tenant-context-safety.sh'));
        preg_match_all('/EXEMPT_RE\+?=/', $script, $m);

        $this->assertNotEmpty($m[0], 'لم تعد قائمة الاستثناءات موجودة');
        $this->assertStringNotContainsString('baseline', strtolower($script),
            'عاد الأساس الواسع — الاستثناء يكون بالاسم والسبب');

        // الملفّات المستثناة التي تستدعي يدويًّا فعلًا
        exec('grep -rEl "TenantContext::(set|reset|bypass)\\(" ' . base_path('app'), $files);
        $this->assertLessThanOrEqual(6, count($files),
            'اتّسعت قائمة الاستثناءات: ' . implode(', ', $files));
    }
}
