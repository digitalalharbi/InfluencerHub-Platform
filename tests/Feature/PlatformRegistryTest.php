<?php

namespace Tests\Feature;

use App\Support\Platforms\PlatformRegistry;
use Tests\TestCase;

/** سجل المنصّات: الأولوية، الإخفاء، تقييد القدرات، منع تجاوز Backend. */
class PlatformRegistryTest extends TestCase
{
    public function test_six_platforms_available_in_priority_order(): void
    {
        $keys = PlatformRegistry::availableKeys();
        $this->assertSame(['snapchat', 'tiktok', 'x', 'linkedin', 'youtube', 'instagram'], $keys);
    }

    public function test_snapchat_and_tiktok_are_first(): void
    {
        $keys = PlatformRegistry::availableKeys();
        $this->assertSame('snapchat', $keys[0]);
        $this->assertSame('tiktok', $keys[1]);
    }

    public function test_draft_future_platform_is_hidden_from_options(): void
    {
        $keys = PlatformRegistry::availableKeys();
        $this->assertNotContains('threads', $keys, 'منصّة مستقبلية بحالة draft يجب ألا تظهر');
        $this->assertFalse(PlatformRegistry::isAvailable('threads'));
        // لكنها مسجّلة (للإدارة)
        $this->assertArrayHasKey('threads', PlatformRegistry::all());
    }

    public function test_capability_gating(): void
    {
        // لينكدإن لا يدعم نشر المحتوى في السجل
        $this->assertFalse(PlatformRegistry::supports('linkedin', 'content_publishing'));
        $this->assertNotContains('linkedin', PlatformRegistry::availableKeys('content_publishing'));
        // لكنها تدعم ملف المبدع
        $this->assertContains('linkedin', PlatformRegistry::availableKeys('creator_profile'));
    }

    public function test_validation_rule_only_allows_available_keys(): void
    {
        $rule = PlatformRegistry::rule('creator_profile');
        $this->assertStringStartsWith('required|in:', $rule);
        $this->assertStringContainsString('snapchat', $rule);
        $this->assertStringNotContainsString('threads', $rule);
    }
}
