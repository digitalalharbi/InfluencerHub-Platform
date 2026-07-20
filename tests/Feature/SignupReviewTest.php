<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Onboarding\Models\{SelfSignup, SignupRequest};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * مراجعة طلبات فتح الحساب.
 *
 * يحرس هذا الملفّ ما لا يظهر في الواجهة: أن القرار يُحفظ فعلًا بسببه ومراجعه.
 * كانت حقول المراجعة خارج قائمة الإسناد الجماعي فتُسقَط بصمت — يُطالَب المراجع
 * بسبب الرفض ويُرسَل للمتقدّم ثم لا يبقى منه أثر.
 */
class SignupReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function admin(): User
    {
        $u = User::create([
            'name' => 'مدير النظام', 'email' => Str::random(6) . '@ex.com',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        // is_system_admin خارج الإسناد الجماعي عمدًا (منع تصعيد الصلاحية)
        $u->forceFill(['is_system_admin' => true])->save();

        return $u;
    }

    private function request(array $attrs = []): SignupRequest
    {
        return SignupRequest::create(array_merge([
            'account_type' => 'agency', 'contact_name' => 'مقدّم', 'email' => 'ask@ex.com',
            'company_name' => 'شركة', 'status' => 'submitted',
        ], $attrs));
    }

    public function test_only_system_admins_reach_the_review_screen(): void
    {
        $req = $this->request();
        $plain = User::create(['name' => 'عادي', 'email' => 'plain@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        $this->get('/beta/admin/signup-requests')->assertRedirect();
        $this->actingAs($plain)->get('/beta/admin/signup-requests')->assertForbidden();
        $this->actingAs($plain)->post("/beta/admin/signup-requests/{$req->id}/approve")->assertForbidden();
    }

    public function test_approval_records_the_decision_and_opens_a_verified_signup_path(): void
    {
        Mail::fake();
        $req = $this->request();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/beta/admin/signup-requests/{$req->id}/approve", ['review_notes' => 'وكالة مؤهّلة'])
            ->assertRedirect();

        $fresh = $req->fresh();
        $this->assertSame('approved', $fresh->status);
        // القرار لا يُحفظ ناقصًا: السبب والمراجع ووقته
        $this->assertSame('وكالة مؤهّلة', $fresh->review_notes);
        $this->assertSame($admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);

        // مسار ذاتي مؤكَّد البريد ليضع صاحب الطلب كلمة مروره بنفسه
        $signup = SelfSignup::where('email', $req->email)->firstOrFail();
        $this->assertSame('verified', $signup->status);
        $this->assertNotNull($signup->email_verified_at);

        Mail::assertSentCount(1);
    }

    public function test_rejection_requires_a_reason_and_stores_it(): void
    {
        Mail::fake();
        $req = $this->request();
        $admin = $this->admin();

        // بلا سبب: لا رفض
        $this->actingAs($admin)
            ->from('/beta/admin/signup-requests')
            ->post("/beta/admin/signup-requests/{$req->id}/reject", [])
            ->assertSessionHasErrors('review_notes');
        $this->assertSame('submitted', $req->fresh()->status);

        $this->actingAs($admin)
            ->post("/beta/admin/signup-requests/{$req->id}/reject", ['review_notes' => 'خارج النطاق'])
            ->assertRedirect();

        $fresh = $req->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('خارج النطاق', $fresh->review_notes, 'ضاع سبب الرفض بعد طلبه وإرساله');
        $this->assertSame($admin->id, $fresh->reviewed_by);
    }

    /** الاعتماد مرّتين كان سيفتح مسارَي تسجيل لنفس الطلب. */
    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        Mail::fake();
        $req = $this->request();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/beta/admin/signup-requests/{$req->id}/approve", ['review_notes' => 'أوّل']);
        $this->actingAs($admin)
            ->from('/beta/admin/signup-requests')
            ->post("/beta/admin/signup-requests/{$req->id}/approve", ['review_notes' => 'ثانٍ'])
            ->assertSessionHasErrors('review');

        $this->assertSame(1, SelfSignup::where('email', $req->email)->count(), 'فُتح مساران لنفس الطلب');
        $this->assertSame('أوّل', $req->fresh()->review_notes, 'داس الاعتماد المكرّر على القرار الأوّل');
    }

    public function test_an_approved_request_cannot_be_rejected(): void
    {
        Mail::fake();
        $req = $this->request();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/beta/admin/signup-requests/{$req->id}/approve", ['review_notes' => 'ok']);
        $this->actingAs($admin)
            ->from('/beta/admin/signup-requests')
            ->post("/beta/admin/signup-requests/{$req->id}/reject", ['review_notes' => 'تراجع'])
            ->assertSessionHasErrors('review');

        $this->assertSame('approved', $req->fresh()->status);
    }

    public function test_every_decision_is_audited_with_its_actor(): void
    {
        Mail::fake();
        $approved = $this->request();
        $rejected = $this->request(['email' => 'other@ex.com']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/beta/admin/signup-requests/{$approved->id}/approve", ['review_notes' => 'ن']);
        $this->actingAs($admin)->post("/beta/admin/signup-requests/{$rejected->id}/reject", ['review_notes' => 'س']);

        $actions = AuditLog::where('action', 'like', 'signup_request.%')->pluck('action')->all();
        $this->assertEqualsCanonicalizing(['signup_request.approved', 'signup_request.rejected'], $actions);
        $this->assertSame($admin->name, AuditLog::where('action', 'like', 'signup_request.%')->first()->actor_name);
    }
}
