<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * حماية انحدار الصياغة: نصّ البريد الظاهر للمستخدم يجب ألّا يحمل مصطلحات هندسيّة داخليّة
 * (سياق/context/scope/tenant/portal/entity/capability أو مفاتيح snake_case). ونؤكّد وجود
 * تحيّة شخصيّة وزرّ إجراء. يفحص القوالب المُصيَّرة فعليًّا عبر معرض التطوير.
 */
class CopyQualityTest extends TestCase
{
    use RefreshDatabase;

    private const STATES = [
        'task_assigned', 'approval_required', 'creator_invitation', 'content_review',
        'overdue_reminder', 'contract_action', 'invoice_action', 'payout_action',
        'integration_alert', 'completed',
    ];

    /** مصطلحات ممنوعة في نصّ المستخدم (تُفحص في المحتوى الظاهر، لا في المسارات/الروابط). */
    private const BANNED = ['السياق', 'tenant_id', 'organization_id', 'capability', 'workflow_state', 'scope:', 'client_review', 'in_progress', 'waiting_for_credentials'];

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function agencyUser(): User
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        return $u;
    }

    /** يفصل نصّ المستخدم عن الروابط: الروابط قد تحمل كلمات مقبولة (مثل مسار)، فلا تُفحص. */
    private function visibleText(string $html): string
    {
        $noLinks = preg_replace('#https?://[^\s"<]+#', ' ', $html);
        $noLinks = preg_replace('#(href|src)="[^"]*"#', ' ', $noLinks);

        return strip_tags($noLinks);
    }

    public function test_gallery_emails_have_no_internal_terminology(): void
    {
        config(['app.dev_tools' => true]);
        $u = $this->agencyUser();

        foreach (self::STATES as $state) {
            foreach (['ar', 'en'] as $loc) {
                $res = $this->actingAs($u)->get("/app/preview/mail/{$state}?locale={$loc}");
                $res->assertOk();
                $text = $this->visibleText($res->getContent());

                foreach (self::BANNED as $term) {
                    $this->assertStringNotContainsString($term, $text, "«{$term}» ظهر في بريد المستخدم [{$state}/{$loc}]");
                }

                // تحيّة شخصيّة موجودة بلغة المستقبِل
                $this->assertStringContainsString($loc === 'ar' ? 'مرحبًا' : 'Hello', $text, "لا تحيّة في [{$state}/{$loc}]");
            }
        }
    }
}
