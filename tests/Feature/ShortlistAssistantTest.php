<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Assistant\{OpenAiAssistant, RuleBasedAssistant, ShortlistAssistant};
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * مساعد ترشيح المؤثرين — يفهم العربية الطبيعية، وجاهز لربط OpenAI بلا ادّعاء.
 */
class ShortlistAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function rule(): RuleBasedAssistant
    {
        return new RuleBasedAssistant;
    }

    public function test_it_extracts_platform_category_budget_and_followers(): void
    {
        $r = $this->rule()->interpret('مؤثرة عناية في الرياض بمتابعين فوق 500 ألف وميزانية 20000');

        $this->assertSame(20000, $r['criteria']['budget_riyals']);
        $this->assertSame(500000, $r['criteria']['min_followers']);
        $this->assertContains('عناية', $r['criteria']['categories']);
        $this->assertNotEmpty($r['understood']);
    }

    public function test_it_reads_platform_keywords(): void
    {
        $this->assertSame('snapchat', $this->rule()->interpret('مشاهير سناب')['criteria']['platform']);
        $this->assertSame('tiktok', $this->rule()->interpret('تيك توك تغطيات')['criteria']['platform']);
    }

    /** الأرقام العربية تُفهَم (كانت تُكسَر بـstrtr غير الآمن للبايت). */
    public function test_it_understands_arabic_numerals(): void
    {
        $r = $this->rule()->interpret('ميزانية ٥٠٠٠ ريال');

        $this->assertSame(5000, $r['criteria']['budget_riyals'] ?? null);
    }

    public function test_units_scale_thousands_and_millions(): void
    {
        $this->assertSame(500000, $this->rule()->interpret('متابعين 500 ألف')['criteria']['min_followers']);
        $this->assertSame(2000000, $this->rule()->interpret('وصول 2 مليون')['criteria']['min_followers']);
    }

    /** بلا مفتاح: OpenAI يعلن عدم جاهزيته ويرتدّ إلى القواعد بصدق — لا ذكاء مُلفَّق. */
    public function test_openai_falls_back_honestly_without_a_key(): void
    {
        $openai = new OpenAiAssistant(null, $this->rule());

        $this->assertFalse($openai->available());
        $r = $openai->interpret('سناب عناية');
        $this->assertSame('snapchat', $r['criteria']['platform']);
        $this->assertStringContainsString('غير مربوط', $r['driver']);
    }

    public function test_resolver_defaults_to_rule_based(): void
    {
        $this->assertInstanceOf(RuleBasedAssistant::class, app(ShortlistAssistant::class));
    }

    /** بمفتاح واستجابة صالحة: يستخرج المعايير من رد النموذج ويوسم السائق openai. */
    public function test_openai_parses_model_json(): void
    {
        Http::fake(['*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'platform' => 'snapchat', 'categories' => ['عناية', 'مكياج'],
                'min_followers' => 500000, 'budget_riyals' => 20000,
            ])]]],
        ])]);

        $openai = new OpenAiAssistant('sk-test', $this->rule(), 'gpt-4o-mini', 'https://api.openai.com/v1');
        $r = $openai->interpret('أبغى مؤثرة عناية بمتابعين فوق نص مليون وميزانية ٢٠ ألف');

        $this->assertSame('openai', $r['driver']);
        $this->assertSame('snapchat', $r['criteria']['platform']);
        $this->assertSame(500000, $r['criteria']['min_followers']);
        $this->assertSame(20000, $r['criteria']['budget_riyals']);
        $this->assertContains('عناية', $r['criteria']['categories']);
        $this->assertNotEmpty($r['understood']);
    }

    /** يُسقط منصّة غير معروفة ويتجاهل الأرقام غير الموجبة (تحقّق sanitize). */
    public function test_openai_sanitizes_bad_fields(): void
    {
        Http::fake(['*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'platform' => 'myspace', 'categories' => ['رياضة', '', 'رياضة'],
                'min_followers' => 0, 'budget_riyals' => -5,
            ])]]],
        ])]);

        $openai = new OpenAiAssistant('sk-test', $this->rule(), 'gpt-4o-mini', 'https://api.openai.com/v1');
        $r = $openai->interpret('رياضة');

        $this->assertSame('openai', $r['driver']);
        $this->assertArrayNotHasKey('platform', $r['criteria']);       // myspace مرفوضة
        $this->assertArrayNotHasKey('min_followers', $r['criteria']);  // 0 يُسقط
        $this->assertArrayNotHasKey('budget_riyals', $r['criteria']);  // سالب يُسقط
        $this->assertSame(['رياضة'], $r['criteria']['categories']);    // تُزال التكرارات والفراغ
    }

    /** عند خطأ HTTP: ارتداد صادق إلى القواعد دون تلفيق. */
    public function test_openai_falls_back_on_http_error(): void
    {
        Http::fake(['*/chat/completions' => Http::response('rate limited', 429)]);

        $openai = new OpenAiAssistant('sk-test', $this->rule(), 'gpt-4o-mini', 'https://api.openai.com/v1');
        $r = $openai->interpret('سناب عناية');

        $this->assertStringContainsString('تعذّر', $r['driver']);
        $this->assertSame('snapchat', $r['criteria']['platform']); // من القواعد
    }

    /** استجابة بلا معايير مفيدة: نستعين بالقواعد كشبكة أمان. */
    public function test_openai_empty_extraction_uses_rule_safety_net(): void
    {
        Http::fake(['*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'platform' => null, 'categories' => [], 'min_followers' => null, 'budget_riyals' => null,
            ])]]],
        ])]);

        $openai = new OpenAiAssistant('sk-test', $this->rule(), 'gpt-4o-mini', 'https://api.openai.com/v1');
        $r = $openai->interpret('تيك توك رياضة');

        $this->assertSame('openai + rule', $r['driver']);
        $this->assertSame('tiktok', $r['criteria']['platform']); // القواعد أمسكتها
    }

    /** تكامل: صفحة الترشيح تستخدم OpenAI فعلًا حين يُضبط السائق ويُربَط المفتاح. */
    public function test_shortlisting_page_uses_openai_when_configured(): void
    {
        config([
            'services.pool_assistant.driver' => 'openai',
            'services.pool_assistant.openai_key' => 'sk-test',
        ]);
        Http::fake(['*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'platform' => 'tiktok', 'categories' => ['رياضة'], 'min_followers' => 100000, 'budget_riyals' => null,
            ])]]],
        ])]);

        $admin = User::create(['name' => 'م', 'email' => 'oa@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $admin->forceFill(['is_system_admin' => true])->save();

        $this->actingAs($admin)->get('/beta/admin/shortlisting?query=' . urlencode('مؤثر رياضة تيك توك'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('assistant.driver', 'openai')
                ->where('assistant.openaiReady', true)
                ->where('criteria.platform', 'tiktok'));
    }

    public function test_only_system_admin_reaches_shortlisting(): void
    {
        $plain = User::create(['name' => 'ع', 'email' => 'p@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        $this->get('/beta/admin/shortlisting')->assertRedirect();
        $this->actingAs($plain)->get('/beta/admin/shortlisting')->assertForbidden();
        $admin = User::create(['name' => 'م', 'email' => 'a@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $admin->forceFill(['is_system_admin' => true])->save();
        $this->actingAs($admin)->get('/beta/admin/shortlisting')->assertOk();
    }
}
