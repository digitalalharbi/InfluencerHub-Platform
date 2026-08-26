<?php

namespace Tests\Feature;

use App\Domain\Communications\Enums\NotificationCategory;
use Tests\TestCase;

/** المصدر القانونيّ الوحيد للفئات: خريطة/قيم/تطبيع آمن للقيم القديمة. */
class NotificationCategoryTest extends TestCase
{
    public function test_map_and_values_are_the_single_source(): void
    {
        $map = NotificationCategory::map();
        $this->assertSame(array_keys($map), NotificationCategory::values());
        $this->assertArrayHasKey('general', $map);
        $this->assertArrayHasKey('finance', $map);
        $this->assertArrayHasKey('reviews', $map);
        // تسميات بشريّة عربيّة (لا مصطلح تقنيّ)
        $this->assertSame('المالية', $map['finance']);
        $this->assertSame('الطلبات', $map['requests']);
    }

    public function test_normalize_maps_unknown_or_legacy_to_general(): void
    {
        $this->assertSame(NotificationCategory::General, NotificationCategory::normalize('brands'));   // فئة قديمة
        $this->assertSame(NotificationCategory::General, NotificationCategory::normalize('tasks'));    // لم تعد موجودة
        $this->assertSame(NotificationCategory::General, NotificationCategory::normalize(null));
        $this->assertSame(NotificationCategory::Finance, NotificationCategory::normalize('finance')); // قانونيّة تبقى
    }
}
