<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientDocument, ClientProfileChangeRequest};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** مراجعات العملاء (الوكالة) React/Inertia — اعتماد/رفض تعديل الملف + مراجعة المستندات + بوابة الدور. */
class InertiaClientReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Tenant,2:Client} */
    private function agent(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        return [$u, $t, $client];
    }

    private function changeRequest(Tenant $t, Client $c, User $u): ClientProfileChangeRequest
    {
        TenantContext::set($t->id);
        $cr = ClientProfileChangeRequest::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'requested_by' => $u->id,
            'changes' => ['legal_name' => ['old' => 'الاسم القديم', 'new' => 'الاسم القانوني الجديد']], 'status' => 'submitted']);
        TenantContext::reset();
        return $cr;
    }

    private function pendingDoc(Tenant $t, Client $c): ClientDocument
    {
        TenantContext::set($t->id);
        $d = ClientDocument::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'category' => 'legal', 'visibility' => 'client_visible',
            'title' => 'سجل تجاري', 'disk' => 'local', 'path' => 'x/' . Str::random(6) . '.pdf', 'original_name' => 'cr.pdf',
            'mime' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 2048, 'checksum_sha256' => str_repeat('c', 64), 'status' => 'pending']);
        TenantContext::reset();
        return $d;
    }

    public function test_index_lists_pending(): void
    {
        [$u, $t, $c] = $this->agent();
        $this->changeRequest($t, $c, $u);
        $this->pendingDoc($t, $c);
        $this->actingAs($u)->get('/beta/client-reviews')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientReviews/Index')
                ->has('changeRequests.data', 1)->has('documents.data', 1)
                ->where('changeRequests.data.0.changes.0.field', 'legal_name'));
    }

    public function test_admin_can_approve_profile(): void
    {
        [$u, $t, $c] = $this->agent('agency_admin');
        $cr = $this->changeRequest($t, $c, $u);
        $this->actingAs($u)->post("/beta/client-reviews/profile/{$cr->id}/approve")->assertRedirect();
        TenantContext::set($t->id);
        $this->assertNotSame('submitted', $cr->fresh()->status);
        TenantContext::reset();
    }

    public function test_reject_profile_requires_note(): void
    {
        [$u, $t, $c] = $this->agent('agency_admin');
        $cr = $this->changeRequest($t, $c, $u);
        $this->actingAs($u)->post("/beta/client-reviews/profile/{$cr->id}/reject", [])->assertSessionHasErrors('note');
        $this->actingAs($u)->post("/beta/client-reviews/profile/{$cr->id}/reject", ['note' => 'بيانات غير مكتملة'])->assertRedirect();
    }

    public function test_review_document(): void
    {
        [$u, $t, $c] = $this->agent('agency_admin');
        $d = $this->pendingDoc($t, $c);
        $this->actingAs($u)->post("/beta/client-reviews/documents/{$d->id}/review", ['decision' => 'approved'])->assertRedirect();
        TenantContext::set($t->id);
        $this->assertNotSame('pending', $d->fresh()->status);
        TenantContext::reset();
    }

    public function test_review_document_rejection_requires_note(): void
    {
        [$u, $t, $c] = $this->agent('agency_admin');
        $d = $this->pendingDoc($t, $c);
        $this->actingAs($u)->post("/beta/client-reviews/documents/{$d->id}/review", ['decision' => 'rejected'])->assertSessionHasErrors('doc');
    }

    public function test_viewer_cannot_approve(): void
    {
        [$u, $t, $c] = $this->agent('viewer');
        $cr = $this->changeRequest($t, $c, $u);
        $this->actingAs($u)->post("/beta/client-reviews/profile/{$cr->id}/approve")->assertForbidden();
    }

    /* ===== /app بعد التحويل من Blade ===== */

    public function test_app_client_reviews_renders_react(): void
    {
        [$u] = $this->agent();
        $this->actingAs($u)->get('/app/client-reviews')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ClientReviews/Index')->where('base', '/app'));
    }

    /**
     * دور العرض يرى الطابور (نفس بوابة Blade: viewAny) لكنه لا يعتمد شيئًا —
     * الحماية على الإجراء لا على الصفحة، وهذا مقصود.
     */
    public function test_app_viewer_sees_queue_but_cannot_approve(): void
    {
        [$u, $t, $client] = $this->agent('viewer');
        $this->actingAs($u)->get('/app/client-reviews')->assertOk();

        TenantContext::bypass(true);
        $cr = ClientProfileChangeRequest::create([
            'tenant_id' => $t->id, 'client_id' => $client->id, 'requested_by' => $u->id,
            'changes' => ['display_name' => 'اسم جديد'], 'status' => 'submitted',
        ]);
        TenantContext::reset();

        $this->actingAs($u)->post("/app/client-reviews/profile/{$cr->id}/approve")->assertForbidden();
    }

    public function test_app_client_reviews_guest_redirected(): void
    {
        $this->get('/app/client-reviews')->assertRedirect('/login');
    }
}
