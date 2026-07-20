<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientDocument, ClientProfileChangeRequest};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 5 — مراجعات الوكالة: تعديلات الملف القانوني + مستندات العميل (تخويل + تطبيق + عزل). */
class AgencyClientReviewTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** يبني مستأجرًا + مؤسّسة + مستخدم وكالة بدور معيّن + عميلًا. */
    private function ctx(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active', 'legal_name' => 'الاسم القديم']);
        TenantContext::reset();
        return [$t, $org, $u, $c];
    }

    private function changeRequest(Tenant $t, Client $c, int $by): ClientProfileChangeRequest
    {
        TenantContext::bypass(true);
        $cr = ClientProfileChangeRequest::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'requested_by' => $by,
            'changes' => ['legal_name' => ['old' => 'الاسم القديم', 'new' => 'الاسم الجديد ش.م.م'], 'tax_number' => ['old' => null, 'new' => '300012345600003']],
            'status' => 'submitted']);
        TenantContext::reset();
        return $cr;
    }

    private function pendingDoc(Tenant $t, Client $c, int $by, string $visibility = 'client_visible'): ClientDocument
    {
        TenantContext::bypass(true);
        $d = ClientDocument::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'category' => 'contract', 'visibility' => $visibility,
            'title' => 'عقد', 'disk' => 'local', 'path' => 'x/y.pdf', 'original_name' => 'c.pdf', 'stored_name' => 'y.pdf',
            'mime' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 10, 'checksum_sha256' => str_repeat('a', 64),
            'version_number' => 1, 'status' => 'pending', 'uploaded_by' => $by]);
        TenantContext::reset();
        return $d;
    }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }

    public function test_approve_profile_change_applies_sensitive_fields(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $cr = $this->changeRequest($t, $c, $u->id);
        $this->actingAs($u)->post("/app/client-reviews/profile/{$cr->id}/approve")->assertRedirect();
        $this->assertEquals('approved', $this->fresh($cr)->status);
        $this->assertEquals('الاسم الجديد ش.م.م', $this->fresh($c)->legal_name);   // طُبّق فعلًا
        $this->assertEquals('300012345600003', $this->fresh($c)->tax_number);
    }

    public function test_reject_profile_change_does_not_apply(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $cr = $this->changeRequest($t, $c, $u->id);
        $this->actingAs($u)->post("/app/client-reviews/profile/{$cr->id}/reject", ['note' => 'المستندات غير كافية'])->assertRedirect();
        $this->assertEquals('rejected', $this->fresh($cr)->status);
        $this->assertEquals('المستندات غير كافية', $this->fresh($cr)->reviewer_note);
        $this->assertEquals('الاسم القديم', $this->fresh($c)->legal_name);         // لم يُطبَّق
    }

    public function test_reject_requires_note(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $cr = $this->changeRequest($t, $c, $u->id);
        $this->actingAs($u)->post("/app/client-reviews/profile/{$cr->id}/reject", [])->assertSessionHasErrors('note');
        $this->assertEquals('submitted', $this->fresh($cr)->status);
    }

    public function test_document_review_records_decision(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $d = $this->pendingDoc($t, $c, $u->id);
        $this->actingAs($u)->post("/app/client-reviews/documents/{$d->id}/review", ['decision' => 'approved'])->assertRedirect();
        $this->assertEquals('approved', $this->fresh($d)->status);
        $this->assertDatabaseHas('client_document_reviews', ['document_id' => $d->id, 'decision' => 'approved']);
    }

    public function test_document_changes_requested_requires_note(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $d = $this->pendingDoc($t, $c, $u->id);
        $this->actingAs($u)->post("/app/client-reviews/documents/{$d->id}/review", ['decision' => 'changes_requested'])->assertSessionHasErrors('doc');
        $this->assertEquals('pending', $this->fresh($d)->status);
    }

    public function test_viewer_role_cannot_approve(): void
    {
        [$t, $org, $u, $c] = $this->ctx('viewer'); // بلا MANAGE_PORTAL
        $cr = $this->changeRequest($t, $c, $u->id);
        $this->actingAs($u)->post("/app/client-reviews/profile/{$cr->id}/approve")->assertForbidden();
        $this->assertEquals('submitted', $this->fresh($cr)->status);
    }

    public function test_cannot_review_other_tenant_change_request(): void
    {
        [$t1, $o1, $u1, $c1] = $this->ctx();
        [$t2, $o2, $u2, $c2] = $this->ctx();
        $crOfT2 = $this->changeRequest($t2, $c2, $u2->id);
        // مستخدم المستأجر 1 يحاول اعتماد طلب المستأجر 2 → Route binding يفشل مغلقًا (404)
        $this->actingAs($u1)->post("/app/client-reviews/profile/{$crOfT2->id}/approve")->assertNotFound();
        $this->assertEquals('submitted', $this->fresh($crOfT2)->status);
    }
}
