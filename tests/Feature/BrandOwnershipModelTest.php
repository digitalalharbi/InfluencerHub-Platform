<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Brand, BrandWorkspaceRelationship, Client};
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * نموذج ملكية العلامة.
 *
 * القرار المعتمَد: العلامة تملك نفسها، والوكالة تحصل على تفويض قابل للإلغاء.
 * قبله كان `brands.tenant_id` دليل الملكية الوحيد و`client_id` قيدًا NOT NULL،
 * فلا وجود لعلامة خارج وكالة — والتسجيل الذاتي ممنوع من الجذر.
 */
class BrandOwnershipModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function tenant(string $type = 'agency'): Tenant
    {
        return Tenant::create(['name' => 'ت', 'slug' => Str::random(8),
            'type' => $type, 'deployment_mode' => 'saas', 'status' => 'active']);
    }

    // ===== الهجرة =====

    /** نوع المستأجر أُضيف ولم يُحمَّل على `deployment_mode`. */
    public function test_tenant_type_is_its_own_axis(): void
    {
        $this->assertTrue(Schema::hasColumn('tenants', 'type'));

        $brandTenant = $this->tenant('brand');
        $this->assertSame('brand', $brandTenant->type);
        $this->assertSame('saas', $brandTenant->deployment_mode,
            'النوع والاستضافة محوران مستقلّان');
    }

    /** العلامة تُنشأ بلا عميل — وهو ما كان مستحيلًا قبل الهجرة. */
    public function test_a_brand_can_exist_without_a_crm_client(): void
    {
        $t = $this->tenant('brand');
        TenantContext::withTenant($t->id, function () use ($t) {
            $b = Brand::create(['tenant_id' => $t->id, 'name' => 'علامة ذاتية', 'slug' => Str::random(6)]);
            $this->assertNull($b->client_id, 'العلامة ما زالت مقيَّدة بعميل');
        });
    }

    /** والعلامة المُدارة تحتفظ بمرجع CRM — الحقل لم يُحذف. */
    public function test_a_managed_brand_keeps_its_crm_reference(): void
    {
        $t = $this->tenant();
        TenantContext::withTenant($t->id, function () use ($t) {
            $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'ع', 'status' => 'active']);
            $b = Brand::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'name' => 'علامة', 'slug' => Str::random(6)]);
            $this->assertSame($c->id, (int) $b->client_id);
        });
    }

    // ===== العلاقات =====

    /** التفويض ليس شاملًا: خدمة غير مذكورة غير مفوَّضة. */
    public function test_delegation_is_scoped_not_total(): void
    {
        [$brand, $agency] = $this->brandManagedBy(['campaigns', 'content']);

        $rel = BrandWorkspaceRelationship::where('brand_id', $brand->id)
            ->where('tenant_id', $agency->id)->first();

        $this->assertTrue($rel->grants('campaigns'));
        $this->assertTrue($rel->grants('content'));
        $this->assertFalse($rel->grants('finance'), 'مُنحت المالية بلا تفويض');
        $this->assertFalse($rel->grants('commerce'));
    }

    /** إلغاء الربط يُنهي العلاقة ولا يمسّ العلامة. */
    public function test_ending_a_relationship_does_not_delete_the_brand(): void
    {
        [$brand, $agency] = $this->brandManagedBy();
        $rel = BrandWorkspaceRelationship::where('brand_id', $brand->id)->first();

        $rel->update(['status' => 'ended', 'ended_at' => now()]);

        $this->assertFalse($rel->fresh()->isLive());
        $this->assertFalse($rel->fresh()->grants('campaigns'), 'تفويض منتهٍ ما زال يمنح');
        TenantContext::withBypass(function () use ($brand) {
            $this->assertNotNull(Brand::find($brand->id), 'حُذفت العلامة بإلغاء الربط');
        });
    }

    /** والملكية لا تنتقل بربط وكالة: صفّ المالك يبقى كما هو. */
    public function test_linking_an_agency_does_not_transfer_ownership(): void
    {
        $brandTenant = $this->tenant('brand');
        $agency = $this->tenant();

        $brand = TenantContext::withTenant($brandTenant->id, fn () => Brand::create([
            'tenant_id' => $brandTenant->id, 'name' => 'علامة', 'slug' => Str::random(6),
        ]));

        BrandWorkspaceRelationship::create(['brand_id' => $brand->id, 'tenant_id' => $brandTenant->id,
            'relationship_type' => BrandWorkspaceRelationship::OWNER, 'status' => 'active', 'started_at' => now()]);
        BrandWorkspaceRelationship::create(['brand_id' => $brand->id, 'tenant_id' => $agency->id,
            'relationship_type' => BrandWorkspaceRelationship::MANAGING_AGENCY, 'status' => 'active',
            'services_scope' => ['campaigns'], 'started_at' => now()]);

        $owner = BrandWorkspaceRelationship::where('brand_id', $brand->id)
            ->where('relationship_type', BrandWorkspaceRelationship::OWNER)->first();

        $this->assertSame($brandTenant->id, (int) $owner->tenant_id, 'انتقلت الملكية بربط وكالة');
    }

    /** ولا تتكرّر العلاقة نفسها بين الطرفين. */
    public function test_a_duplicate_relationship_of_the_same_type_is_refused(): void
    {
        [$brand, $agency] = $this->brandManagedBy();

        $this->expectException(\Illuminate\Database\QueryException::class);
        BrandWorkspaceRelationship::create(['brand_id' => $brand->id, 'tenant_id' => $agency->id,
            'relationship_type' => BrandWorkspaceRelationship::MANAGING_AGENCY, 'status' => 'active']);
    }

    /** ويمكن لأكثر من وكالة أن تُفوَّض بنطاقين مختلفين. */
    public function test_two_agencies_may_hold_different_scopes(): void
    {
        [$brand, $first] = $this->brandManagedBy(['campaigns']);
        $second = $this->tenant();

        BrandWorkspaceRelationship::create(['brand_id' => $brand->id, 'tenant_id' => $second->id,
            'relationship_type' => BrandWorkspaceRelationship::SERVICE_PROVIDER, 'status' => 'active',
            'services_scope' => ['ads'], 'started_at' => now()]);

        $rels = BrandWorkspaceRelationship::where('brand_id', $brand->id)->get();
        $this->assertCount(2, $rels);
        $this->assertTrue($rels->firstWhere('tenant_id', $first->id)->grants('campaigns'));
        $this->assertFalse($rels->firstWhere('tenant_id', $first->id)->grants('ads'));
        $this->assertTrue($rels->firstWhere('tenant_id', $second->id)->grants('ads'));
    }

    // ===== الترحيل =====

    /** كل علامة قائمة لها علاقة إدارة واحدة — بلا فقد ولا تكرار. */
    public function test_backfill_gives_every_brand_exactly_one_managing_relationship(): void
    {
        $t = $this->tenant();
        TenantContext::withTenant($t->id, function () use ($t) {
            $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'ع', 'status' => 'active']);
            foreach (range(1, 3) as $i) {
                Brand::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'name' => "ع{$i}", 'slug' => Str::random(6)]);
            }
        });

        // يحاكي ما فعلته الهجرة على البيانات القائمة
        $brands = DB::table('brands')->select('id', 'tenant_id')->get();
        foreach ($brands as $b) {
            DB::table('brand_workspace_relationships')->insertOrIgnore([
                'brand_id' => $b->id, 'tenant_id' => $b->tenant_id,
                'relationship_type' => 'managing_agency', 'status' => 'active',
                'services_scope' => json_encode(['campaigns']), 'started_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->assertSame($brands->count(), DB::table('brand_workspace_relationships')->count());
        $this->assertSame(0, DB::table('brands')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('brand_workspace_relationships')
                ->whereColumn('brand_workspace_relationships.brand_id', 'brands.id'))
            ->count(), 'علامة بلا علاقة بعد الترحيل');
    }

    /** @return array{0:Brand,1:Tenant} */
    private function brandManagedBy(array $scope = ['campaigns', 'content', 'finance']): array
    {
        $agency = $this->tenant();
        $brand = TenantContext::withTenant($agency->id, function () use ($agency) {
            $c = Client::create(['tenant_id' => $agency->id, 'client_number' => 'CL-' . Str::random(4),
                'display_name' => 'ع', 'status' => 'active']);

            return Brand::create(['tenant_id' => $agency->id, 'client_id' => $c->id,
                'name' => 'علامة', 'slug' => Str::random(6)]);
        });

        BrandWorkspaceRelationship::create(['brand_id' => $brand->id, 'tenant_id' => $agency->id,
            'relationship_type' => BrandWorkspaceRelationship::MANAGING_AGENCY, 'status' => 'active',
            'services_scope' => $scope, 'started_at' => now()]);

        return [$brand, $agency];
    }
}
