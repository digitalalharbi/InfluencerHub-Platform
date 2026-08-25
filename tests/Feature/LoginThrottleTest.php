<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * تحديد معدّل تسجيل الدخول (§5) — يمنع التخمين العنيف قبل تقديم أقوى حساب في المنتج
 * (مالك المنصّة). مفتاح مركّب (بريد+IP)، ردّ عامّ (429) لا يكشف وجود بريد.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('login:' . sha1('brute@ex.com') . '|127.0.0.1');
        parent::tearDown();
    }

    public function test_login_is_throttled_after_repeated_attempts(): void
    {
        // 20 محاولة مسموحة لكل (بريد+IP)/دقيقة، ثم تُحظر.
        for ($i = 0; $i < 20; $i++) {
            $res = $this->post('/login', ['email' => 'brute@ex.com', 'password' => 'wrong-' . $i]);
            $this->assertNotSame(429, $res->getStatusCode(), "attempt {$i} should not be throttled yet");
        }
        $this->post('/login', ['email' => 'brute@ex.com', 'password' => 'wrong-21'])->assertStatus(429);
    }

    public function test_throttle_response_does_not_reveal_account_existence(): void
    {
        // القفل (429) لا يختلف بين بريد موجود وغير موجود — لا account enumeration.
        for ($i = 0; $i < 21; $i++) {
            $this->post('/login', ['email' => 'brute@ex.com', 'password' => 'x']);
        }
        $blocked = $this->post('/login', ['email' => 'brute@ex.com', 'password' => 'x']);
        $blocked->assertStatus(429);
        // لا يُفصح المحتوى عن حالة الحساب (مجرّد رسالة حدّ عامّة).
        $this->assertStringNotContainsString('is_system_admin', $blocked->getContent() ?: '');
    }
}
