<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * لا روابط/مسارات وثائق تطويرية داخلية في واجهة المستأجر.
 *
 * ظهر «docs/EXTERNAL-BLOCKERS.md» في شريطي التكاملات والإعدادات على الإنتاج —
 * لغة تطويرية داخلية لا محل لها في تجربة المستأجر. هذه تمنع عودتها كنصّ مرئي
 * (تُستثنى أسطر التعليقات، فهي لا تُعرَض).
 */
class NoInternalDocLinksInUiTest extends TestCase
{
    /** مسارات وثائق داخلية يجب ألّا تظهر كنصّ في صفحات الواجهة. */
    private array $forbidden = ['docs/EXTERNAL-BLOCKERS', 'docs/PRODUCT-TERMINOLOGY', 'docs/PRODUCTION-FUNCTIONAL-AUDIT'];

    private function isCommentLine(string $line): bool
    {
        $t = ltrim($line);

        return str_starts_with($t, '*')
            || str_starts_with($t, '//')
            || str_starts_with($t, '/*')
            || str_starts_with($t, '{/*'); // تعليق JSX
    }

    public function test_no_internal_doc_paths_in_tenant_pages(): void
    {
        $dir = resource_path('js/Pages');
        $this->assertDirectoryExists($dir);

        $hits = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== 'tsx') {
                continue;
            }
            $rel = str_replace(resource_path('js/Pages') . '/', '', $f->getPathname());
            foreach (file($f->getPathname()) as $n => $line) {
                if ($this->isCommentLine($line)) {
                    continue;
                }
                foreach ($this->forbidden as $needle) {
                    if (str_contains($line, $needle)) {
                        $hits[] = "{$rel}:" . ($n + 1) . " → {$needle}";
                    }
                }
            }
        }

        $this->assertEmpty($hits, "مسارات وثائق داخلية مرئية في الواجهة:\n" . implode("\n", $hits));
    }
}
