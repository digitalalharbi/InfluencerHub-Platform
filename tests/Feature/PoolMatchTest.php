<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\AdminPool\Services\PoolMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * محرّك ترشيح القاعدة — درجة قابلة للتفسير من بيانات حقيقية.
 */
class PoolMatchTest extends TestCase
{
    use RefreshDatabase;

    private function svc(): PoolMatchService
    {
        return new PoolMatchService;
    }

    private function creator(array $a = []): PoolCreator
    {
        return PoolCreator::create(array_merge([
            'name' => 'م', 'platform' => 'snapchat', 'account_url' => 'https://s/' . uniqid(),
            'followers' => 600000, 'tier' => 'A', 'categories' => ['عناية', 'يوميات'],
            'price_coverage_minor' => 800000, 'phone' => '0500000000',
        ], $a));
    }

    public function test_platform_and_category_match_raise_the_score_with_reasons(): void
    {
        $m = $this->svc()->score(
            ['platform' => 'snapchat', 'categories' => ['عناية']],
            $this->creator(),
        );

        $this->assertGreaterThan(50, $m['score']);
        $this->assertContains('المنصّة مطابقة', $m['reasons']);
        $this->assertContains('كل المجالات مطابقة', $m['reasons']);
    }

    public function test_wrong_platform_is_flagged_not_silently_scored(): void
    {
        $m = $this->svc()->score(['platform' => 'tiktok'], $this->creator(['platform' => 'snapchat']));

        $this->assertContains('منصّة مختلفة', $m['flags']);
    }

    public function test_budget_fit_is_scored_and_overflow_flagged(): void
    {
        $within = $this->svc()->score(['budget_minor' => 1000000], $this->creator(['price_coverage_minor' => 800000]));
        $over = $this->svc()->score(['budget_minor' => 500000], $this->creator(['price_coverage_minor' => 800000]));

        $this->assertContains('ضمن الميزانية', $within['reasons']);
        $this->assertContains('يتجاوز الميزانية', $over['flags']);
    }

    public function test_missing_contact_is_flagged(): void
    {
        $m = $this->svc()->score([], $this->creator(['phone' => null]));

        $this->assertContains('بلا وسيلة تواصل', $m['flags']);
    }

    public function test_score_never_exceeds_100(): void
    {
        $m = $this->svc()->score(
            ['platform' => 'snapchat', 'categories' => ['عناية', 'يوميات'], 'min_followers' => 100000, 'budget_minor' => 1000000],
            $this->creator(),
        );

        $this->assertLessThanOrEqual(100, $m['score']);
    }

    public function test_no_criteria_means_no_matching_mode(): void
    {
        $this->assertFalse($this->svc()->hasCriteria([]));
        $this->assertTrue($this->svc()->hasCriteria(['platform' => 'snapchat']));
    }
}
