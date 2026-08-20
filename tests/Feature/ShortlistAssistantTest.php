<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Assistant\{OpenAiAssistant, RuleBasedAssistant, ShortlistAssistant};
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
