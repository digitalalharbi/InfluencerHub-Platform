<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanEntitlement, PlanVersion};
use App\Domain\Content\Models\ContentItem;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** محتوى المبدع React/Inertia — إنشاء/تعديل/تقديم معزول عبر ContentWorkflowService. */
class InertiaCreatorContentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Creator,2:Tenant} */
    private function creatorUser(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $pv = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_portal.enabled', 'value' => 1]);
        (new CreateSubscription)->handle($org, $pv);
        $u = User::create(['name' => 'مبدع', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        $seq = Creator::withTrashed()->count() + 1;
        $c = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-' . $seq, 'type' => 'influencer',
            'display_name' => 'نورة', 'status' => 'active', 'user_id' => $u->id, 'financial_verification_status' => 'not_provided']);
        TenantContext::reset();
        return [$u, $c, $t];
    }

    private function draft(Tenant $t, Creator $c, string $status = 'draft'): ContentItem
    {
        TenantContext::set($t->id);
        $it = ContentItem::create(['tenant_id' => $t->id, 'content_number' => 'CT-' . $t->id . '-' . Str::random(3),
            'creator_id' => $c->id, 'type' => 'reel', 'title' => 'مقطع', 'status' => $status, 'version' => 1]);
        TenantContext::reset();
        return $it;
    }

    public function test_list_renders(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        $it = $this->draft($t, $c);
        $this->actingAs($u)->get('/beta/creator/content')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreatorPortal/Content/Index')
                ->where('todo', 1)->where('items.data.0.id', $it->id)->has('types'));
    }

    public function test_create_draft(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        $this->actingAs($u)->post('/beta/creator/content', ['title' => 'ريلز جديد', 'type' => 'reel'])
            ->assertRedirect('/beta/creator/content');
        TenantContext::set($t->id);
        $this->assertSame(1, ContentItem::where('creator_id', $c->id)->where('title', 'ريلز جديد')->count());
        TenantContext::reset();
    }

    public function test_update_and_submit(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        $it = $this->draft($t, $c);
        $this->actingAs($u)->post("/beta/creator/content/{$it->id}/update", ['title' => 'محدّث', 'media_url' => 'https://x.test/v'])
            ->assertRedirect();
        $this->actingAs($u)->post("/beta/creator/content/{$it->id}/submit")->assertRedirect();
        TenantContext::set($t->id);
        $fresh = $it->fresh();
        $this->assertSame('محدّث', $fresh->title);
        $this->assertNotSame('draft', $fresh->status);
        TenantContext::reset();
    }

    public function test_create_validates_type(): void
    {
        [$u] = $this->creatorUser();
        $this->actingAs($u)->post('/beta/creator/content', ['title' => 'x', 'type' => 'bogus'])->assertSessionHasErrors('type');
    }

    public function test_idor_safe(): void
    {
        [$u1] = $this->creatorUser();
        [, $c2, $t2] = $this->creatorUser();
        $itB = $this->draft($t2, $c2);
        $this->actingAs($u1)->get("/beta/creator/content/{$itB->id}")->assertNotFound();
    }

    // ===== غياب قائمة الربط يُفسَّر لا يُخفى =====

    /**
     * الربط بالتعاون هو ما يوصل المحتوى بالحملة ومراجعة العميل والفاتورة.
     *
     * القائمة تعرض التعاونات `in_progress` فقط، فالمبدع الذي قبل تعاونًا ولم
     * يبدأه يرى النموذج بلا حقل ربط ولا سبب — طريق مسدود صامت. العدّ يُرسَل
     * لتقول الواجهة السبب والخطوة.
     */
    public function test_an_accepted_but_unstarted_collaboration_is_announced(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        $this->collaboration($t, $c, 'accepted');

        $this->actingAs($u)->get('/beta/creator/content')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('collabs', [])                 // لا شيء للربط
                ->where('notStartedCollabs', 1));      // والسبب معلَن
    }

    /** وبعد «بدء العمل» يصير التعاون قابلًا للربط ويسقط التفسير. */
    public function test_a_started_collaboration_becomes_linkable(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        $col = $this->collaboration($t, $c, 'in_progress');

        $this->actingAs($u)->get('/beta/creator/content')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('collabs.0.id', $col->id)
                ->where('notStartedCollabs', 0));
    }

    private function collaboration(Tenant $t, Creator $c, string $status): \App\Domain\Collaborations\Models\Collaboration
    {
        TenantContext::set($t->id);
        $col = \App\Domain\Collaborations\Models\Collaboration::create([
            'tenant_id' => $t->id, 'collaboration_number' => 'CO-' . $t->id . '-' . Str::random(3),
            'creator_id' => $c->id, 'title' => 'تعاون', 'status' => $status, 'fee_minor' => 100000,
        ]);
        TenantContext::reset();

        return $col;
    }
}
