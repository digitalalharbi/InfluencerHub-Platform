<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * الجذر صفحة عامة لا تحويلة إلى لوحة داخلية.
     *
     * كان هذا الاختبار يثبّت السلوك القديم: `/` يحوّل إلى `/app`، فيهبط زائر
     * بلا حساب داخل واجهة تشغيل ثم يُدفع إلى الدخول. تغيّر المتطلَّب: الزائر
     * يرى المنتَج أوّلًا. التحويل بحسب الدور محروس في PublicSiteTest.
     */
    public function test_root_serves_the_public_site_to_a_visitor(): void
    {
        // المحتوى داخل حمولة Inertia لا في HTML مباشرةً، فيُفحص المكوّن لا النصّ
        $this->get('/')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/Gateway'));
    }
}
