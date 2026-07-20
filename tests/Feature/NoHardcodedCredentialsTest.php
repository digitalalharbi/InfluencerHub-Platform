<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Phase 5 hardening — لا كلمات مرور/أسرار ثابتة في المصدر (Seeders/Commands/Docs/Config/Routes). */
class NoHardcodedCredentialsTest extends TestCase
{
    /** أنماط ممنوعة في كود المصدر (بيانات معاينة/إنتاج). */
    private array $forbidden = ['Password123!', 'password123', 'admin123', 'secret123'];

    /** مسارات المصدر التي يجب ألا تحوي بيانات اعتماد ثابتة (خارج fixtures الاختبارات). */
    private function sourceFiles(): array
    {
        $roots = ['app', 'database/seeders', 'database/migrations', 'config', 'routes', 'docs'];
        $files = [];
        foreach ($roots as $root) {
            $dir = base_path($root);
            if (! is_dir($dir)) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (in_array($f->getExtension(), ['php', 'md', 'txt', 'js'], true)) $files[] = $f->getPathname();
            }
        }
        return $files;
    }

    public function test_no_hardcoded_preview_credentials_in_source(): void
    {
        $hits = [];
        foreach ($this->sourceFiles() as $file) {
            $content = file_get_contents($file);
            foreach ($this->forbidden as $needle) {
                if (str_contains($content, $needle)) {
                    $hits[] = basename($file) . " → '{$needle}'";
                }
            }
        }
        $this->assertEmpty($hits, "بيانات اعتماد ثابتة في المصدر: \n" . implode("\n", $hits));
    }

    public function test_seed_commands_refuse_production(): void
    {
        // preview:seed و e2e:seed يجب أن يرفضا الإنتاج
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('preview:seed')->assertFailed();
        $this->artisan('e2e:seed')->assertExitCode(1);
        $this->app->detectEnvironment(fn () => 'testing');
    }

    public function test_preview_center_404_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        // Preview Center يعيد 404 في الإنتاج (نتحقق من الـController مباشرة عبر الطريق)
        $ctrl = new \App\Http\Controllers\Web\PreviewCenterController();
        try {
            $ctrl->index();
            $this->fail('كان يجب 404 في الإنتاج');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            $this->assertTrue(true);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }
}
