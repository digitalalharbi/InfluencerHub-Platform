<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * اتّساق المصطلحات (§35): اسم واحد لكل مفهوم. تحرس هذه الاختبارات ملفّات اللغة من
 * عودة الصيغ المطوّلة المحظورة التي كانت تتعارض مع docs/PRODUCT-TERMINOLOGY.md
 * والقائمة المعتمدة في resources/js/lib/nav.ts.
 */
class TerminologyConsistencyTest extends TestCase
{
    /** الصيغ المحظورة → البديل المعتمد (مرجع بشريّ). */
    private const BANNED = [
        'العلامات التجارية' => 'العلامات',
        'طلبات الخدمة' => 'الطلبات',
        'المحتوى والموافقات' => 'المحتوى',
        'مستحقات المبدعين والمدفوعات' => 'المستحقات',
    ];

    public function test_arabic_lang_files_carry_no_banned_compound_terms(): void
    {
        foreach ([lang_path('ar/navigation.php'), lang_path('ar/entities.php')] as $file) {
            $contents = file_get_contents($file);
            foreach (self::BANNED as $banned => $canonical) {
                $this->assertStringNotContainsString(
                    $banned,
                    $contents,
                    "«{$banned}» صيغة محظورة في {$file}؛ استعمل «{$canonical}»."
                );
            }
        }
    }

    public function test_canonical_terms_are_present_in_navigation(): void
    {
        $nav = file_get_contents(lang_path('ar/navigation.php'));
        // المصطلحات المعتمدة موجودة فعلًا (لا حذف بلا بديل)
        foreach (['العلامات', 'الطلبات', 'المحتوى', 'المستحقات', 'صناع المحتوى'] as $canonical) {
            $this->assertStringContainsString($canonical, $nav, "المصطلح المعتمد «{$canonical}» مفقود.");
        }
    }
}
