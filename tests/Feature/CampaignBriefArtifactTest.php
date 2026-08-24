<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\Client;
use App\Domain\Exports\Models\ExportJob;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أثر مستند موحّد للملخّص الآمن للعميل: المعاينة (inline) والتنزيل (attachment)
 * يبثّان **نفس** البايتات (sha256 متطابق). تغيّر المصدر لا يجدّد صامتًا؛ التجديد
 * الصريح يُنشئ نسخة جديدة بينما تبقى القديمة ثابتة. مقصور بالمستأجر (Policy view).
 */
class CampaignBriefArtifactTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:Tenant,1:User,2:Campaign} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
            $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-7', 'client_id' => $cl->id,
                'name' => 'حملة الصيف', 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR']);
            return [$t, $u, $cm];
        });
    }

    public function test_preview_and_download_stream_identical_bytes_and_reuse_one_artifact(): void
    {
        Storage::fake('local');
        [$t, $u, $cm] = $this->world();

        $preview = $this->actingAs($u)->get("/app/campaigns/{$cm->id}/client-brief/preview");
        $preview->assertOk();
        $preview->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $preview->headers->get('content-disposition'));
        $previewBytes = $preview->getContent();

        $download = $this->actingAs($u)->get("/app/campaigns/{$cm->id}/client-brief/download");
        $download->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('content-disposition'));
        $downloadBytes = $download->getContent();

        $this->assertStringStartsWith('%PDF-', $previewBytes);
        $this->assertSame(hash('sha256', $previewBytes), hash('sha256', $downloadBytes), 'المعاينة والتنزيل نفس البايتات');

        // أثر واحد فقط أُعيد استخدامه (لا توليد مزدوج)
        $count = TenantContext::withBypass(fn () => ExportJob::where('type', 'campaign_client_brief')->count());
        $this->assertSame(1, $count);
    }

    public function test_source_change_marks_stale_without_silent_regeneration_then_regenerate_makes_new_version(): void
    {
        Storage::fake('local');
        [$t, $u, $cm] = $this->world();

        // نسخة أولى
        $this->actingAs($u)->get("/app/campaigns/{$cm->id}/client-brief/preview")->assertOk();
        $first = TenantContext::withBypass(fn () => ExportJob::where('type', 'campaign_client_brief')->latest('id')->first());
        $firstChecksum = $first->checksum;

        // تغيّر المصدر (اسم الحملة) → صفحة الحملة تُبلّغ بالقِدَم، والمعاينة تُعيد النسخة القديمة (لا تجديد صامت)
        TenantContext::withBypass(fn () => $cm->update(['name' => 'حملة الشتاء']));
        $this->actingAs($u)->get("/app/campaigns/{$cm->id}")
            ->assertInertia(fn ($p) => $p->where('documents.clientBrief.stale', true)->where('documents.clientBrief.hasArtifact', true));
        $stillOne = TenantContext::withBypass(fn () => ExportJob::where('type', 'campaign_client_brief')->count());
        $this->assertSame(1, $stillOne, 'لا تجديد صامت عند تغيّر المصدر');

        // تجديد صريح → نسخة جديدة، والقديمة تبقى ثابتة على القرص
        $this->actingAs($u)->post("/app/campaigns/{$cm->id}/client-brief/regenerate")->assertRedirect();
        $two = TenantContext::withBypass(fn () => ExportJob::where('type', 'campaign_client_brief')->count());
        $this->assertSame(2, $two);
        $first->refresh();
        Storage::disk('local')->assertExists($first->path);
        $this->assertSame($firstChecksum, $first->checksum, 'النسخة القديمة ثابتة');
        // بعد التجديد لم تعد الصفحة قديمة
        $this->actingAs($u)->get("/app/campaigns/{$cm->id}")
            ->assertInertia(fn ($p) => $p->where('documents.clientBrief.stale', false));
    }

    public function test_preview_is_tenant_scoped(): void
    {
        Storage::fake('local');
        [$t, $u, $cm] = $this->world();
        [$t2, $stranger] = (function () { $w = $this->world(); return [$w[0], $w[1]]; })();

        // مستخدم من مستأجر آخر لا يصل لأثر حملة غيره
        $this->actingAs($stranger)->get("/app/campaigns/{$cm->id}/client-brief/preview")->assertNotFound();
    }
}
