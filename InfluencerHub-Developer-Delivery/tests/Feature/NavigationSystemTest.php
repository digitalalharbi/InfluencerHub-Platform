<?php

namespace Tests\Feature;

use App\Support\Navigation\NavigationBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * نظام التنقّل والمصطلحات المركزي — تكامل الإعداد + القواميس + العدّادات.
 * يضمن عدم وجود عنصر قائمة بلا تسمية، ولا مفتاح شارة غير معروف، ولا انحراف بين العربية والإنجليزية.
 */
class NavigationSystemTest extends TestCase
{
    use RefreshDatabase;

    private const KNOWN_BADGE_KEYS = [
        'service_requests', 'creator_applications', 'content',
        'brand_reviews', 'client_reviews', 'client_notifications',
    ];

    public function test_every_nav_item_has_a_label_in_both_locales(): void
    {
        foreach (config('navigation') as $portal => $portalConfig) {
            foreach ($portalConfig['groups'] ?? [] as $group) {
                // عنوان المجموعة
                $groupKey = $group['key'];
                $this->assertNotSame("navigation.groups.$groupKey", __("navigation.groups.$groupKey"),
                    "مجموعة بلا تسمية عربية: $portal/$groupKey");

                foreach ($group['items'] ?? [] as $item) {
                    $key = $item['key'];
                    foreach (['ar', 'en'] as $locale) {
                        app()->setLocale($locale);
                        $label = __("navigation.items.$key");
                        $this->assertNotSame("navigation.items.$key", $label,
                            "عنصر بلا تسمية [$locale]: $portal/$key");
                    }
                }
            }
        }
    }

    public function test_all_badge_keys_are_known(): void
    {
        foreach (config('navigation') as $portalConfig) {
            foreach ($portalConfig['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    if (! empty($item['badge'])) {
                        $this->assertContains($item['badge'], self::KNOWN_BADGE_KEYS,
                            "مفتاح شارة غير معروف: {$item['badge']}");
                    }
                }
            }
        }
    }

    public function test_status_dictionary_is_symmetric_across_locales(): void
    {
        $ar = require base_path('lang/ar/statuses.php');
        $en = require base_path('lang/en/statuses.php');
        $this->assertSame(array_keys($ar), array_keys($en), 'قاموس الحالات غير متماثل بين العربية والإنجليزية');
        $this->assertSame(array_keys($ar['tone']), array_keys($en['tone']));
    }

    public function test_navigation_badges_returns_non_negative_ints(): void
    {
        NavigationBadges::flush();
        $badges = NavigationBadges::all();
        $this->assertIsArray($badges);
        // في قاعدة نظيفة لا يوجد عمل معلّق → مصفوفة فارغة (تُحذف الأصفار).
        $this->assertSame([], $badges, 'قاعدة فارغة يجب أن تُعيد صفر شارات');
        // العقد: كل قيمة عدد صحيح موجب عند وجودها، والوصول الآمن لا يرمي أخطاء.
        foreach ($badges as $count) {
            $this->assertIsInt($count);
            $this->assertGreaterThan(0, $count);
        }
        $this->assertSame(0, NavigationBadges::for('service_requests'));
    }

    public function test_soon_items_have_no_badge_and_are_marked(): void
    {
        foreach (config('navigation') as $portalConfig) {
            foreach ($portalConfig['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    if (! empty($item['soon'])) {
                        $this->assertArrayNotHasKey('badge', $item, 'عنصر "قريبًا" لا يجب أن يحمل شارة عدّاد');
                    }
                }
            }
        }
    }

    /**
     * كل حالة دورة حياة فعلية في النطاق لها تسمية+نغمة في القاموس المركزي (بلا تسريب مفتاح خام).
     * يحرس أماكن مثل CampaignAnalytics::timeline() التي تستدعي __() بلا احتياطي.
     */
    public function test_all_domain_lifecycle_statuses_resolve_in_lexicon(): void
    {
        $statuses = array_unique(array_merge(
            \App\Domain\Collaborations\Models\Collaboration::STATUSES,
            \App\Domain\Finance\Models\Payout::STATUSES, // مستحقات
            ['draft', 'submitted', 'agency_review', 'changes_requested', 'client_review', 'approved', 'scheduled', 'published', 'rejected'], // محتوى
            ['draft', 'sent', 'signed', 'active', 'completed', 'terminated', 'cancelled'], // عقود
            ['draft', 'planning', 'active', 'paused', 'completed', 'cancelled'], // حملات
            ['draft', 'submitted', 'partially_approved', 'approved', 'rejected'], // إصدارات الترشيح
            ['planned', 'assigned', 'in_progress', 'submitted', 'approved', 'published', 'cancelled'], // مخرجات الحملة
        ));
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach ($statuses as $s) {
                $this->assertNotSame("statuses.$s", __("statuses.$s"), "حالة بلا تسمية [$locale]: $s");
                $tone = __("statuses.tone.$s");
                $this->assertFalse(str_starts_with($tone, 'statuses.'), "حالة بلا نغمة: $s");
            }
        }
    }
}
