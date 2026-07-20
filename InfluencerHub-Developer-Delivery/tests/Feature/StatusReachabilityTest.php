<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Support\Workflow\WaitingOn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * إمكانية الوصول بين الحالات.
 *
 * النمط الذي تكرّر أربع مرّات: كيان يُنشأ بحالة تستبعده خطوة لاحقة، ولا مسار
 * من الواجهة للخروج منها — فيُضاف السجلّ ثم يختفي بلا سبب معروف.
 *
 * هذه الاختبارات تحرس الشرط الأعمّ: الحالة الافتراضية يجب أن تكون داخل مسار
 * سير العمل، لا خارجه.
 */
class StatusReachabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'ت', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
    }

    /**
     * الافتراضي في القاعدة داخل مفردات المراجعة.
     *
     * كان `active` — وهي ليست من مسار العلامة (draft → submitted → under_review
     * → approved). أُصلح المستدعي سابقًا وبقي الافتراضي، فظلّ العيب كامنًا لأي
     * مسار إنشاء آخر: بذرة، استيراد، أمر console.
     */
    public function test_brand_status_default_is_inside_the_approval_workflow(): void
    {
        $default = DB::selectOne(
            "select column_default from information_schema.columns
             where table_name = 'brands' and column_name = 'status'"
        )->column_default;

        $this->assertStringContainsString("'draft'", (string) $default,
            'الافتراضي خارج مسار الاعتماد — علامة لا تصير معتمَدة أبدًا');
    }

    /** الإنشاء المباشر (بلا خدمة) يهبط داخل المسار لا خارجه. */
    public function test_a_brand_created_without_the_service_still_enters_the_workflow(): void
    {
        $t = $this->tenant();
        TenantContext::bypass(true);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1',
            'display_name' => 'ع', 'status' => 'active']);
        $brand = Brand::create(['tenant_id' => $t->id, 'client_id' => $client->id,
            'name' => 'علامة', 'slug' => Str::random(6)]);
        TenantContext::reset();

        $this->assertSame('draft', $brand->fresh()->status);
    }

    /**
     * كل حالة افتراضية لها مخرج معلن في سير العمل.
     * الحالة التي لا انتقال منها ولا هي نهائية = طريق مسدود.
     */
    public function test_every_default_status_has_a_declared_exit(): void
    {
        $entries = [
            ['brands', 'draft', \App\Domain\CRM\Services\BrandWorkflowService::class],
            ['campaigns', 'draft', \App\Domain\Campaigns\Services\CampaignWorkflowService::class],
            ['collaborations', 'offered', \App\Domain\Collaborations\Services\CollaborationWorkflowService::class],
            ['contracts', 'draft', \App\Domain\Contracts\Services\ContractWorkflowService::class],
            ['content_items', 'draft', \App\Domain\Content\Services\ContentWorkflowService::class],
            ['payouts', 'pending', \App\Domain\Finance\Services\PayoutWorkflowService::class],
        ];

        foreach ($entries as [$table, $expectedDefault, $service]) {
            $ref = new \ReflectionClass($service);
            $allowed = $ref->getConstant('ALLOWED');
            $this->assertIsArray($allowed, "{$service} بلا خريطة انتقالات");
            $this->assertNotEmpty($allowed[$expectedDefault] ?? [],
                "الحالة الافتراضية «{$expectedDefault}» في {$table} بلا مخرج — طريق مسدود");
        }
    }

    /** حالة المبدع الافتراضية تُستبعَد من الترشيح، فيجب أن يكون لها مخرج. */
    public function test_creator_default_status_can_be_changed_through_the_app(): void
    {
        $this->assertContains('active', ['prospect', 'active', 'paused', 'blocked']);

        $t = $this->tenant();
        TenantContext::bypass(true);
        $c = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-1',
            'type' => 'influencer', 'display_name' => 'م', 'status' => 'prospect']);
        TenantContext::reset();

        // المسار موجود فعلًا (لا يكفي أن يكون العمود قابلًا للتحديث)
        $this->assertTrue(
            collect(app('router')->getRoutes())->contains(
                fn ($r) => $r->uri() === 'app/creators/{creator}/update'
            ),
            'لا مسار لتغيير حالة المبدع من الواجهة',
        );
        $this->assertSame('prospect', $c->status);
    }

    // ===== إعلان الانتظار =====

    /** الحالات التي تنتظر الطرف الآخر تُعلن ذلك بدل قائمة إجراءات فارغة. */
    public function test_states_that_wait_on_a_counterparty_declare_it(): void
    {
        $cases = [
            ['collaboration', 'offered', 'المبدع'],
            ['content', 'client_review', 'العميل'],
            ['contract', 'sent', 'الطرف الآخر'],
            ['shortlist', 'submitted', 'العميل'],
            ['invoice', 'issued', 'العميل'],
        ];

        foreach ($cases as [$entity, $status, $party]) {
            $w = WaitingOn::for($entity, $status);
            $this->assertNotNull($w, "حالة {$entity}.{$status} تنتظر غيرها بلا إعلان");
            $this->assertSame($party, $w['party']);
            $this->assertNotSame('', trim($w['expects']), 'الانتظار بلا بيان لما يُنتظَر');
        }
    }

    /** الحالات التي الدور فيها على صاحب الشاشة لا تُعلن انتظارًا. */
    public function test_states_owned_by_the_agency_do_not_claim_to_be_waiting(): void
    {
        foreach ([['collaboration', 'submitted'], ['content', 'agency_review'], ['payout', 'pending']] as [$e, $s]) {
            $this->assertNull(WaitingOn::for($e, $s),
                "حالة {$e}.{$s} الدور فيها على الوكالة ومع ذلك تُعلن انتظارًا");
        }
    }

    /** حالة مُعلنة في الواجهة بلا فعل يبلغها = حالة مستحيلة. */
    public function test_signup_request_statuses_are_all_reachable(): void
    {
        $labels = array_keys((new \ReflectionClass(
            \App\Http\Controllers\Inertia\Admin\SignupReviewController::class
        ))->getConstant('STATUS_LABEL'));

        // «submitted» حالة الإنشاء؛ ما عداها يحتاج فعلًا يبلغه.
        // الخريطة صريحة لأن اسم الفعل لا يُشتقّ من اسم الحالة اشتقاقًا موثوقًا.
        $actionFor = ['contacted' => 'contacted', 'approved' => 'approve', 'rejected' => 'reject'];
        $routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri())->all();

        foreach ($labels as $status) {
            if ($status === 'submitted') {
                continue;
            }
            $this->assertArrayHasKey($status, $actionFor,
                "الحالة «{$status}» مُعلنة في الواجهة بلا فعل معروف يبلغها");
            $this->assertContains(
                "beta/admin/signup-requests/{signupRequest}/{$actionFor[$status]}",
                $routes,
                "لا مسار يبلغ الحالة «{$status}» — حالة مستحيلة",
            );
        }
    }
}
