<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Brand, Client, ClientMember};
use App\Domain\CRM\Services\BrandWorkflowService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Phase 5 — سير عمل العلامات: draft→submit→review→approve/changes، append-only، versioning، عزل. */
class BrandWorkflowTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id . '-1', 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $reviewer = User::create(['name' => 'r', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        TenantContext::reset();
        return [$t, $c, $reviewer];
    }
    private function wf(): BrandWorkflowService { return app(BrandWorkflowService::class); }
    private function fresh(Brand $b): Brand { TenantContext::bypass(true); $f = $b->fresh(); TenantContext::reset(); return $f; }

    public function test_full_happy_path_draft_to_approved(): void
    {
        [$t, $c, $rev] = $this->ctx();
        $b = $this->wf()->createDraft($t->id, $c->id, ['name' => 'نايك'], $rev->id);
        $this->assertEquals('draft', $b->status);
        $b = $this->wf()->submit($b, $rev->id);
        $this->assertEquals('submitted', $b->status);
        $b = $this->wf()->startReview($b, $rev->id);
        $this->assertEquals('under_review', $b->status);
        $b = $this->wf()->approve($b, $rev->id, 'ممتاز');
        $this->assertEquals('approved', $b->status);
        TenantContext::bypass(true);
        $this->assertEquals(1, $b->versions()->count());
        $this->assertDatabaseHas('brand_review_decisions', ['brand_id' => $b->id, 'decision' => 'approved']);
        // history append-only: 4 انتقالات
        $this->assertEquals(4, \App\Domain\CRM\Models\BrandStatusHistory::where('brand_id', $b->id)->count());
        TenantContext::reset();
    }

    public function test_changes_requested_then_resubmit_creates_new_version(): void
    {
        [$t, $c, $rev] = $this->ctx();
        $b = $this->wf()->createDraft($t->id, $c->id, ['name' => 'نايك'], $rev->id);
        $b = $this->wf()->submit($b, $rev->id);
        $b = $this->wf()->startReview($b, $rev->id);
        $b = $this->wf()->requestChanges($b, $rev->id, 'حدّث الوصف');
        $this->assertEquals('changes_requested', $this->fresh($b)->status);
        $this->assertEquals('حدّث الوصف', $this->fresh($b)->changes_reason);
        // العميل يعدّل ثم يعيد الإرسال → إصدار جديد
        $b = $this->wf()->updateDraft($this->fresh($b), ['name' => 'نايك', 'description' => 'وصف محدّث'], $rev->id);
        $b = $this->wf()->submit($this->fresh($b), $rev->id);
        $this->assertEquals('submitted', $b->status);
        $this->assertEquals(2, $b->current_version);              // إصدار جديد
    }

    public function test_locked_after_submit_cannot_edit(): void
    {
        [$t, $c, $rev] = $this->ctx();
        $b = $this->wf()->createDraft($t->id, $c->id, ['name' => 'x'], $rev->id);
        $b = $this->wf()->submit($b, $rev->id);
        $this->expectException(\RuntimeException::class); // مقفلة بعد الإرسال
        $this->wf()->updateDraft($this->fresh($b), ['name' => 'y'], $rev->id);
    }

    public function test_invalid_transition_blocked(): void
    {
        [$t, $c, $rev] = $this->ctx();
        $b = $this->wf()->createDraft($t->id, $c->id, ['name' => 'x'], $rev->id);
        // draft → approved غير مسموح (يجب المرور بالمراحل)
        $this->expectException(\RuntimeException::class);
        $this->wf()->approve($b, $rev->id);
    }

    public function test_double_approve_blocked(): void
    {
        [$t, $c, $rev] = $this->ctx();
        $b = $this->wf()->createDraft($t->id, $c->id, ['name' => 'x'], $rev->id);
        $b = $this->wf()->submit($b, $rev->id);
        $b = $this->wf()->startReview($b, $rev->id);
        $b = $this->wf()->approve($b, $rev->id);
        $this->expectException(\RuntimeException::class); // approved → approved غير مسموح
        $this->wf()->approve($this->fresh($b), $rev->id);
    }

    public function test_client_only_sees_own_client_brands_over_http(): void
    {
        [$t, $c, $rev] = $this->ctx();
        TenantContext::bypass(true);
        $u = User::create(['name' => 'cu', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        // علامة لعميل آخر في نفس المستأجر
        $c2 = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-x', 'display_name' => 'عميل2', 'type' => 'company', 'status' => 'active']);
        $other = $this->wf()->createDraft($t->id, $c2->id, ['name' => 'علامة أخرى ZZZ'], $rev->id);
        TenantContext::reset();
        $mine = $this->wf()->createDraft($t->id, $c->id, ['name' => 'علامتي'], $u->id);
        $this->actingAs($u)->get('/client/brands')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('brands', fn ($brands) => collect($brands)->pluck('name')->contains('علامتي')
                    && ! collect($brands)->pluck('name')->contains('علامة أخرى ZZZ')));
        $this->actingAs($u)->get("/client/brands/{$other->id}")->assertNotFound(); // IDOR
    }
}
