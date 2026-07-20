<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Creators\Models\{Creator, CreatorPlatform, CreatorService};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 — CRUD بوابة المبدع: منصات/خدمات + رفع صورة + منع IDOR على الحذف. */
class CreatorPortalCrudTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function creator(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $pv = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_portal.enabled', 'value' => 1]);
        (new CreateSubscription)->handle($org, $pv);
        $u = User::create(['name' => 'c', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        $c = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-1', 'type' => 'influencer',
            'display_name' => 'نورة', 'status' => 'active', 'user_id' => $u->id]);
        TenantContext::reset();
        return [$u, $c, $t];
    }

    public function test_creator_adds_and_deletes_platform(): void
    {
        [$u, $c] = $this->creator();
        $this->actingAs($u)->post('/creator/platforms', ['platform' => 'instagram', 'handle' => 'me', 'followers_count' => 100])->assertRedirect();
        TenantContext::bypass(true);
        $p = CreatorPlatform::where('creator_id', $c->id)->first();
        TenantContext::reset();
        $this->assertNotNull($p);
        $this->actingAs($u)->post("/creator/platforms/{$p->id}/delete")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertEquals(0, CreatorPlatform::where('creator_id', $c->id)->count());
        TenantContext::reset();
    }

    public function test_creator_adds_service_price_stored_minor(): void
    {
        [$u, $c] = $this->creator();
        $this->actingAs($u)->post('/creator/services', ['service_type' => 'post', 'price' => 1500, 'delivery_days' => 3])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertDatabaseHas('creator_services', ['creator_id' => $c->id, 'price_minor' => 150000]); // بلا float
        TenantContext::reset();
    }

    public function test_avatar_upload_stores_private(): void
    {
        Storage::fake('local');
        [$u, $c] = $this->creator();
        $this->actingAs($u)->post('/creator/avatar', ['file' => UploadedFile::fake()->image('a.png')])->assertRedirect();
        TenantContext::bypass(true);
        $fresh = $c->fresh();
        TenantContext::reset();
        $this->assertNotNull($fresh->avatar_path);
        Storage::disk('local')->assertExists($fresh->avatar_path);
    }

    public function test_creator_cannot_delete_another_creators_platform_idor(): void
    {
        [$uA, $cA] = $this->creator();
        [$uB, $cB, $tB] = $this->creator();
        TenantContext::bypass(true);
        $pB = CreatorPlatform::create(['tenant_id' => $tB->id, 'creator_id' => $cB->id, 'platform' => 'tiktok', 'handle' => 'b']);
        TenantContext::reset();
        // المبدع A يحاول حذف منصة B → 404 (منع IDOR)
        $this->actingAs($uA)->post("/creator/platforms/{$pB->id}/delete")->assertNotFound();
        TenantContext::bypass(true);
        $this->assertEquals(1, CreatorPlatform::where('creator_id', $cB->id)->count()); // لم تُحذف
        TenantContext::reset();
    }
}
