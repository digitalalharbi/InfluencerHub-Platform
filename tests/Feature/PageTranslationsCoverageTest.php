<?php

namespace Tests\Feature;

use App\Support\I18n\PageTranslations;
use Tests\TestCase;

/**
 * حارس تقسيم الترجمة (PERF-01) — يمنع كشف مفاتيح خام: كل مجموعة ترجمة يستعملها مكوّن
 * صفحة يجب أن تكون ضمن CORE أو خريطة PageTranslations لمجلّده. أي صفحة جديدة تستعمل
 * مجموعة غير مُخرَّطة تُسقِط هذا الاختبار (وإلا ظهرت بالإنجليزية كمفتاح/fallback).
 */
class PageTranslationsCoverageTest extends TestCase
{
    public function test_every_page_component_namespace_is_shipped(): void
    {
        $core = PageTranslations::CORE;
        $map = PageTranslations::map();
        $known = array_unique(array_merge($core, ...array_values($map)));

        $root = resource_path('js/Pages');
        $this->assertDirectoryExists($root);

        $violations = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
                continue;
            }
            $rel = ltrim(str_replace($root, '', $file->getPathname()), '/');
            $folder = explode('/', $rel)[0];
            // مجلّد الصفحة الأعلى فقط (المكوّن يُخرَّط بجزئه الأول)
            if (str_contains($folder, '.')) {
                $folder = pathinfo($folder, PATHINFO_FILENAME);
            }
            $allowed = array_merge($core, $map[$folder] ?? []);

            $src = (string) file_get_contents($file->getPathname());
            // t('ns.…') و t("ns.…") و t(`ns.…`)
            preg_match_all('/\bt\(\s*[\'"`]([a-z_]+)\./', $src, $m);
            foreach (array_unique($m[1]) as $ns) {
                if (! in_array($ns, $known, true)) {
                    continue; // ليست مجموعة ترجمة مُدارة (مثل مفتاح محلّي)
                }
                if (! in_array($ns, $allowed, true)) {
                    $violations[] = "$rel يستعمل «$ns» غير المشمولة لمجلّد «$folder»";
                }
            }
        }

        $this->assertSame([], $violations, "مجموعات ترجمة غير مُرسَلة:\n".implode("\n", $violations));
    }
}
