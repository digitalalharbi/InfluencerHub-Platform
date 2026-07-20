<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * كلٌّ إلى مساحته.
 *
 * القرار المحروس: **العلامة تُفحَص قبل الوكالة**. مالك العلامة عضوٌ في مؤسّسة
 * أيضًا، فحصُ «هل له عضوية فعّالة؟» يصدق عليه — ولو سُئل أوّلًا لَذهب إلى
 * `/app`، مساحة الوكالة. وقع ذلك فعلًا وظهر حين فُتح الجذر بحساب علامة.
 *
 * وهذه الاختبارات تحرس الترتيب لا النتيجة وحدها: إعادةُ ترتيب الشروط تكسرها.
 */
class PortalRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function user(string $name = 'م'): User
    {
        return User::create([
            'name' => $name,
            'email' => Str::random(8).'@ex.test',
            'password' => bcrypt('secret-pass-123'),
            'is_active' => true,
        ]);
    }

    private function tenant(string $type): Tenant
    {
        return Tenant::create([
            'name' => 'ت', 'slug' => Str::random(10), 'type' => $type,
            'deployment_mode' => 'saas', 'status' => 'active',
        ]);
    }

    /** مالك علامة كامل: مستأجر `brand` + مؤسّسة + عضوية + علاقة ملكية. */
    private function brandOwner(): User
    {
        $tenant = $this->tenant(Tenant::TYPE_BRAND);
        $user = $this->user('مالك');

        TenantContext::withBypass(function () use ($tenant, $user) {
            $org = Organization::create(['tenant_id' => $tenant->id, 'name' => 'ع',
                'slug' => Str::random(10), 'type' => 'brand', 'status' => 'active']);

            OrganizationMembership::create(['tenant_id' => $tenant->id, 'organization_id' => $org->id,
                'user_id' => $user->id, 'role' => Role::BrandAdmin->value, 'status' => 'active']);

            $brand = Brand::create(['tenant_id' => $tenant->id, 'name' => 'علامتي',
                'slug' => 'b-'.Str::random(6), 'status' => 'approved', 'current_version' => 1]);

            BrandWorkspaceRelationship::create(['brand_id' => $brand->id, 'tenant_id' => $tenant->id,
                'relationship_type' => BrandWorkspaceRelationship::OWNER, 'status' => 'active',
                'services_scope' => BrandWorkspaceRelationship::SERVICES, 'started_at' => now()]);
        });

        return $user;
    }

    private function agencyMember(): User
    {
        $tenant = $this->tenant(Tenant::TYPE_AGENCY);
        $user = $this->user('وكيل');

        TenantContext::withBypass(function () use ($tenant, $user) {
            $org = Organization::create(['tenant_id' => $tenant->id, 'name' => 'و',
                'slug' => Str::random(10), 'type' => 'agency', 'status' => 'active']);

            OrganizationMembership::create(['tenant_id' => $tenant->id, 'organization_id' => $org->id,
                'user_id' => $user->id, 'role' => Role::AgencyAdmin->value, 'status' => 'active']);
        });

        return $user;
    }

    private function creator(): User
    {
        $tenant = $this->tenant(Tenant::TYPE_AGENCY);
        $user = $this->user('مبدع');

        TenantContext::withBypass(fn () => Creator::create([
            'tenant_id' => $tenant->id, 'creator_number' => 'CR-'.Str::random(6),
            'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active',
            'user_id' => $user->id,
        ]));

        return $user;
    }

    // ===== الوجهة لكل دور =====

    public function test_a_guest_sees_the_gateway(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_a_brand_owner_lands_in_the_brand_workspace(): void
    {
        $this->actingAs($this->brandOwner())->get('/')->assertRedirect('/brand');
    }

    public function test_an_agency_member_lands_in_the_agency_workspace(): void
    {
        $this->actingAs($this->agencyMember())->get('/')->assertRedirect('/app');
    }

    public function test_a_creator_lands_in_the_creator_portal(): void
    {
        $this->actingAs($this->creator())->get('/creator')->assertOk();
        $this->actingAs($this->creator())->get('/')->assertRedirect('/creator');
    }

    // ===== `/start` يتبع القاعدة نفسها =====

    /**
     * من يعمل بالفعل لا يُعرض عليه تسجيل.
     *
     * ولو عرضه لَأنشأ حسابًا ثانيًا لنفسه — والمنطق منسوخٌ لو كُتب مرّتين،
     * فيفترق المتحكّمان.
     */
    public function test_start_sends_a_signed_in_user_to_their_workspace_not_a_signup_form(): void
    {
        $this->actingAs($this->brandOwner())->get('/start')->assertRedirect('/brand');
        $this->actingAs($this->agencyMember())->get('/start')->assertRedirect('/app');
        $this->actingAs($this->creator())->get('/start')->assertRedirect('/creator');
    }

    // ===== جلسات متعدّدة =====

    /**
     * ثلاث جلسات في عملية واحدة لا تتلوّث ببعضها.
     *
     * سياق المستأجر حالة ساكنة، وتسريبُه بين الطلبات يُرسل مستخدمًا إلى مساحة
     * غيره — وهو عطل لا يظهر إلا حين يعمل أكثر من دور معًا.
     */
    public function test_switching_between_roles_never_crosses_destinations(): void
    {
        $brand = $this->brandOwner();
        $agency = $this->agencyMember();
        $creator = $this->creator();

        foreach ([[$brand, '/brand'], [$agency, '/app'], [$creator, '/creator'], [$brand, '/brand']] as [$user, $expected]) {
            $this->actingAs($user)->get('/')->assertRedirect($expected);
        }

        // والزائر بعدهم يرى البوّابة — لا بقايا جلسة سابقة.
        // `forgetGuards` لازم: `actingAs` يُثبّت المستخدم لبقيّة الاختبار.
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertOk();
    }

    /** حساب بلا أيّ انتماء لا يسقط ولا يدور — يذهب إلى بوّابة العميل. */
    public function test_a_user_with_no_membership_has_a_defined_destination(): void
    {
        $this->actingAs($this->user())->get('/')->assertRedirect('/client');
    }

    // ===== لا حلقات تحويل =====

    /**
     * @dataProvider publicPaths
     */
    public function test_public_paths_do_not_loop_for_a_guest(string $path): void
    {
        $r = $this->get($path);

        $this->assertContains($r->status(), [200, 301, 302], "حالة غير متوقّعة على {$path}");

        if ($r->isRedirect()) {
            $target = parse_url((string) $r->headers->get('Location'), PHP_URL_PATH);
            $this->assertNotSame($path, $target, "حلقة تحويل: {$path} يشير إلى نفسه");
            $this->get($target)->assertOk();
        }
    }

    public static function publicPaths(): array
    {
        return [['/'], ['/start'], ['/register'], ['/register/account-type'], ['/start?type=brand']];
    }
}
