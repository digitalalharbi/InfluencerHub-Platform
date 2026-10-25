<?php

namespace Tests\Feature;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Models\CreatorNote;
use App\Domain\Creators\Models\CreatorTask;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** CREATOR-01 — طبقة علاقة المبدع + الخطّ الزمني الموحّد. بيانات حقيقية، RBAC، عزل، IDOR. */
class CreatorRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:Tenant,1:User,2:Creator} */
    private function agencyWithCreator(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $c = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer',
            'display_name' => 'مبدع', 'status' => 'active', 'followers_count' => 120000]);
        TenantContext::reset();

        return [$t, $u, $c];
    }

    public function test_show_includes_relationship_timeline_notes_tasks(): void
    {
        [, $u, $c] = $this->agencyWithCreator();
        $this->actingAs($u)->get("/app/creators/{$c->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Creators/Show')
                ->has('relationship')->has('timeline')->has('notes')->has('tasks')
                ->where('canManage', true));
    }

    public function test_update_relationship_persists(): void
    {
        [, $u, $c] = $this->agencyWithCreator();
        $this->actingAs($u)->post("/app/creators/{$c->id}/relationship", [
            'relationship_stage' => 'engaged', 'relationship_health' => 'healthy',
            'source' => 'مؤتمر', 'preferred_channel' => 'whatsapp', 'do_not_contact' => false,
            'next_follow_up_at' => now()->addWeek()->format('Y-m-d'),
        ])->assertRedirect();

        $c->refresh();
        $this->assertSame('engaged', $c->relationship_stage);
        $this->assertSame('healthy', $c->relationship_health);
        $this->assertSame('whatsapp', $c->preferred_channel);
        $this->assertNotNull($c->next_follow_up_at);
    }

    public function test_add_note_persists_updates_last_interaction_and_shows_in_timeline(): void
    {
        [$t, $u, $c] = $this->agencyWithCreator();
        $this->actingAs($u)->post("/app/creators/{$c->id}/notes", ['body' => 'اتصلنا اليوم'])->assertRedirect();

        TenantContext::bypass(true);
        $this->assertSame(1, CreatorNote::where('creator_id', $c->id)->count());
        TenantContext::reset();
        $c->refresh();
        $this->assertNotNull($c->last_interaction_at);

        $res = $this->actingAs($u)->get("/app/creators/{$c->id}")->assertOk();
        $timeline = collect($res->viewData('page')['props']['timeline']);
        $this->assertTrue($timeline->contains(fn ($e) => $e['type'] === 'note' && str_contains($e['text'], 'اتصلنا اليوم')));
    }

    public function test_add_and_complete_task(): void
    {
        [, $u, $c] = $this->agencyWithCreator();
        $this->actingAs($u)->post("/app/creators/{$c->id}/tasks", ['title' => 'إرسال العرض', 'due_at' => now()->addDays(3)->format('Y-m-d')])->assertRedirect();
        TenantContext::bypass(true);
        $task = CreatorTask::where('creator_id', $c->id)->firstOrFail();
        $this->assertSame('open', $task->status);
        TenantContext::reset();

        $this->actingAs($u)->post("/app/creators/{$c->id}/tasks/{$task->id}/complete")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('done', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        TenantContext::reset();
    }

    public function test_timeline_aggregates_real_events_in_reverse_chronological_order(): void
    {
        [$t, $u, $c] = $this->agencyWithCreator();
        TenantContext::bypass(true);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id, 'name' => 'حملة', 'status' => 'active', 'budget_minor' => 100000, 'currency' => 'SAR', 'created_by' => $u->id]);
        // created_at ليست fillable — تُضبط صراحةً بعد الإنشاء لاختبار الترتيب الزمني
        Collaboration::create(['tenant_id' => $t->id, 'campaign_id' => $cm->id, 'collaboration_number' => 'CO-'.Str::random(5), 'creator_id' => $c->id, 'title' => 'ت', 'fee_minor' => 500000, 'currency' => 'SAR', 'status' => 'accepted'])
            ->forceFill(['created_at' => now()->subDays(2)])->save();
        CreatorNote::create(['tenant_id' => $t->id, 'creator_id' => $c->id, 'author_id' => $u->id, 'body' => 'ملاحظة حديثة'])
            ->forceFill(['created_at' => now()])->save();
        TenantContext::reset();

        $res = $this->actingAs($u)->get("/app/creators/{$c->id}")->assertOk();
        $timeline = collect($res->viewData('page')['props']['timeline']);
        $this->assertGreaterThanOrEqual(2, $timeline->count());
        // الأحدث أولًا: الملاحظة (اليوم) قبل التعاون (قبل يومين)
        $this->assertSame('note', $timeline->first()['type']);
        $ts = $timeline->pluck('ts')->all();
        $sorted = $ts; rsort($sorted);
        $this->assertSame($sorted, $ts, 'الخطّ الزمني غير مرتّب تنازليًّا');
    }

    public function test_complete_task_is_idor_guarded_across_creators(): void
    {
        [$t, $u, $cA] = $this->agencyWithCreator();
        TenantContext::bypass(true);
        $cB = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-B-'.$t->id, 'type' => 'influencer', 'display_name' => 'ب', 'status' => 'active', 'followers_count' => 1000]);
        $taskB = CreatorTask::create(['tenant_id' => $t->id, 'creator_id' => $cB->id, 'created_by' => $u->id, 'title' => 'مهمة ب', 'status' => 'open']);
        TenantContext::reset();

        // محاولة إنجاز مهمة المبدع ب عبر مسار المبدع أ ⇒ 404
        $this->actingAs($u)->post("/app/creators/{$cA->id}/tasks/{$taskB->id}/complete")->assertNotFound();
        TenantContext::bypass(true);
        $this->assertSame('open', $taskB->fresh()->status);
        TenantContext::reset();
    }

    public function test_relationship_is_tenant_isolated(): void
    {
        [, , $cA] = $this->agencyWithCreator();
        [, $uB] = $this->agencyWithCreator();
        $this->actingAs($uB)->get("/app/creators/{$cA->id}")->assertNotFound();
        $this->actingAs($uB)->post("/app/creators/{$cA->id}/notes", ['body' => 'x'])->assertNotFound();
    }

    public function test_viewer_cannot_mutate_relationship(): void
    {
        [, $viewer, $c] = $this->agencyWithCreator('viewer');
        $this->actingAs($viewer)->post("/app/creators/{$c->id}/relationship", ['relationship_stage' => 'active'])->assertForbidden();
        $this->actingAs($viewer)->post("/app/creators/{$c->id}/notes", ['body' => 'x'])->assertForbidden();
    }
}
