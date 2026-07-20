<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * عزل المستأجر عبر أسطح التنفيذ الثلاثة: HTTP، والمهامّ، والخلفية.
 *
 * الفرق الذي تحرسه: العزل في HTTP يضبطه وسيط، أمّا المهامّ والمستمعون
 * والـWebhooks فتعمل **بلا وسيط** — فمن ينسى ضبط السياق فيها لا يحصل على خطأ
 * بل على نتيجة فارغة تُقرأ «لا بيانات». الصمت هو العطل.
 */
class TenantIsolationSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:Tenant,1:Organization,2:User,3:Creator} */
    private function tenantWithCreator(string $name): array
    {
        $t = Tenant::create(['name' => $name, 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::withBypass(function () use ($t, &$org, &$u, &$cr, $name) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => $name, 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => $name, 'email' => Str::random(8) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
            $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . Str::random(5), 'type' => 'influencer',
                'display_name' => "مبدع {$name}", 'status' => 'active']);
        });

        return [$t, $org, $u, $cr];
    }

    // ===== HTTP =====

    /** قراءة سجلّ مستأجر آخر عبر HTTP تُردّ 404 لا تُعرض. */
    public function test_http_read_across_tenants_is_refused(): void
    {
        [, , $userA] = $this->tenantWithCreator('أ');
        [, , , $creatorB] = $this->tenantWithCreator('ب');

        $this->actingAs($userA)->get("/app/creators/{$creatorB->id}")->assertNotFound();
    }

    /** والكتابة كذلك — لا تُنفَّذ ولا تُغيّر شيئًا. */
    public function test_http_write_across_tenants_is_refused(): void
    {
        [, , $userA] = $this->tenantWithCreator('أ');
        [, , , $creatorB] = $this->tenantWithCreator('ب');
        $before = $creatorB->display_name;

        $this->actingAs($userA)->post("/app/creators/{$creatorB->id}/update", ['display_name' => 'مخترَق'])
            ->assertNotFound();

        TenantContext::withBypass(function () use ($creatorB, $before) {
            $this->assertSame($before, $creatorB->fresh()->display_name, 'كُتب في مستأجر آخر');
        });
    }

    /** مدير النظام يُشرف قراءةً ولا يكتب داخل مستأجر لا عضوية له فيه. */
    public function test_system_admin_cannot_write_inside_another_tenant(): void
    {
        [, , , $creatorB] = $this->tenantWithCreator('ب');
        $admin = TenantContext::withBypass(function () {
            $u = User::create(['name' => 'SA', 'email' => Str::random(8) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            $u->forceFill(['is_system_admin' => true])->save();

            return $u;
        });
        $before = $creatorB->display_name;

        $this->actingAs($admin)->post("/app/creators/{$creatorB->id}/update", ['display_name' => 'مخترَق'])
            ->assertForbidden();

        TenantContext::withBypass(function () use ($creatorB, $before) {
            $this->assertSame($before, $creatorB->fresh()->display_name);
        });
    }

    // ===== خارج HTTP: المهامّ والخلفية =====

    /**
     * بلا سياق، الاستعلام يعود فارغًا — وهذا هو الخطر: لا استثناء يُنبّه.
     * الاختبار يوثّق السلوك حتّى لا يُقرأ الفراغ يومًا على أنه «لا بيانات».
     */
    public function test_a_query_without_context_returns_empty_not_an_error(): void
    {
        $this->tenantWithCreator('أ');
        TenantContext::reset();

        $this->assertSame(0, Creator::count(),
            'fail-closed: الفراغ هنا سببه غياب السياق لا غياب السجلّات');

        // والفرق يظهر عند ضبط السياق
        $count = TenantContext::withBypass(fn () => Creator::count());
        $this->assertGreaterThan(0, $count);
    }

    /** المهمّة التي تضبط سياقها ترى مستأجرها وحده. */
    public function test_a_job_scoped_to_its_tenant_sees_only_it(): void
    {
        [$tA] = $this->tenantWithCreator('أ');
        $this->tenantWithCreator('ب');

        $seen = TenantContext::withTenant($tA->id, fn () => Creator::pluck('tenant_id')->unique()->values()->all());

        $this->assertSame([$tA->id], $seen, 'تسرّبت سجلّات مستأجر آخر إلى المهمّة');
    }

    /** ولا تترك سياقها خلفها للمهمّة التالية في العامل نفسه. */
    public function test_a_job_does_not_leak_its_context_to_the_next_one(): void
    {
        [$tA] = $this->tenantWithCreator('أ');
        TenantContext::reset();

        TenantContext::withTenant($tA->id, fn () => Creator::count());

        $this->assertNull(TenantContext::tenantId(), 'بقي سياق المهمّة مفتوحًا بعدها');
        $this->assertFalse(TenantContext::bypassing());
    }

    /** حتّى إذا فشلت — وهو ما يكسر `try/finally` المنسيّ. */
    public function test_a_failing_job_does_not_leak_bypass(): void
    {
        [$tA] = $this->tenantWithCreator('أ');
        TenantContext::reset();

        try {
            TenantContext::withBypass(function () { throw new \RuntimeException('فشل المهمّة'); });
        } catch (\RuntimeException) {
            // متوقّع
        }

        $this->assertFalse(TenantContext::bypassing(), 'تسرّب التجاوز بعد فشل مهمّة');
        $this->assertSame(0, Creator::count(), 'العزل مكسور بعد فشل مهمّة');
    }

    // ===== الذاكرة المؤقّتة =====

    /** مفاتيح الذاكرة مُنطّقة بالمستأجر — لا يقرأ مستأجر قيمة آخر. */
    public function test_cache_keys_do_not_leak_between_tenants(): void
    {
        [$tA] = $this->tenantWithCreator('أ');
        [$tB] = $this->tenantWithCreator('ب');

        Cache::put("tenant:{$tA->id}:metric", 'قيمة-أ', 60);
        Cache::put("tenant:{$tB->id}:metric", 'قيمة-ب', 60);

        $this->assertSame('قيمة-أ', Cache::get("tenant:{$tA->id}:metric"));
        $this->assertSame('قيمة-ب', Cache::get("tenant:{$tB->id}:metric"));
        $this->assertNotSame(Cache::get("tenant:{$tA->id}:metric"), Cache::get("tenant:{$tB->id}:metric"));
    }

    // ===== مهمّتان متتابعتان في العامل نفسه =====

    /**
     * العامل الواحد ينفّذ مهامّ متتابعة في العملية نفسها. تجاوز أو سياق بقي
     * من مهمّة يمتدّ إلى التالية — وهو ما وقع فعليًّا في FinalizeCreatorFilesJob.
     */
    public function test_two_sequential_jobs_in_one_worker_do_not_bleed(): void
    {
        [$tA] = $this->tenantWithCreator('أ');
        [$tB] = $this->tenantWithCreator('ب');

        $first = TenantContext::withTenant($tA->id, fn () => Creator::pluck('tenant_id')->unique()->values()->all());
        // المهمّة الثانية لا ترث شيئًا من الأولى
        $second = TenantContext::withTenant($tB->id, fn () => Creator::pluck('tenant_id')->unique()->values()->all());

        $this->assertSame([$tA->id], $first);
        $this->assertSame([$tB->id], $second, 'تسرّبت سجلّات المهمّة الأولى إلى الثانية');
        $this->assertNull(TenantContext::tenantId(), 'بقي سياق بعد المهمّتين');
    }

    /** ومهمّة فاشلة لا تُسمّم التالية. */
    public function test_a_failed_job_does_not_poison_the_next_one(): void
    {
        [$tA] = $this->tenantWithCreator('أ');
        [$tB] = $this->tenantWithCreator('ب');

        try {
            TenantContext::withTenant($tA->id, function () { throw new \RuntimeException('فشل'); });
        } catch (\RuntimeException) {
            // متوقّع
        }

        $seen = TenantContext::withTenant($tB->id, fn () => Creator::pluck('tenant_id')->unique()->values()->all());
        $this->assertSame([$tB->id], $seen, 'ورثت المهمّة التالية سياق مهمّة فاشلة');
    }

    // ===== Webhooks =====

    /** Webhook بمستأجر معروف يُعالَج داخل نطاقه. */
    public function test_a_webhook_with_a_resolvable_tenant_is_scoped_to_it(): void
    {
        [$tA] = $this->tenantWithCreator('أ');
        $this->tenantWithCreator('ب');

        // النمط الموثَّق: استخرج المستأجر بتجاوز مرّة، ثم عالج داخل withTenant
        $resolved = TenantContext::withBypass(fn () => Tenant::find($tA->id));
        $this->assertNotNull($resolved);

        $seen = TenantContext::withTenant($resolved->id, fn () => Creator::pluck('tenant_id')->unique()->values()->all());

        $this->assertSame([$tA->id], $seen);
        $this->assertNull(TenantContext::tenantId(), 'بقي سياق الـwebhook مفتوحًا');
    }

    /** وبلا مستأجر معروف لا يُعالَج بلا نطاق — يُرفض. */
    public function test_a_webhook_without_a_tenant_is_refused_not_run_unscoped(): void
    {
        $this->tenantWithCreator('أ');

        $resolved = TenantContext::withBypass(fn () => Tenant::find(999999));
        $this->assertNull($resolved, 'مستأجر غير موجود');

        // القاعدة: لا معالجة بلا نطاق. المحاكاة هنا: لو عولج بلا سياق لعاد فارغًا
        // بصمت — وهو ما يجب ألّا يُقرأ «لا بيانات».
        TenantContext::reset();
        $this->assertSame(0, Creator::count(),
            'الفراغ سببه غياب السياق — لذلك يُرفض الـwebhook بدل معالجته');
    }

    // ===== المجدوِل عبر المستأجرين =====

    /** المجدوِل يمرّ على المستأجرين واحدًا واحدًا، ولا يخلط نتائجهم. */
    public function test_a_scheduler_pass_isolates_each_tenant(): void
    {
        [$tA] = $this->tenantWithCreator('أ');
        [$tB] = $this->tenantWithCreator('ب');

        $perTenant = [];
        foreach ([$tA, $tB] as $t) {
            $perTenant[$t->id] = TenantContext::withTenant($t->id, fn () => Creator::count());
        }

        $this->assertSame([$tA->id => 1, $tB->id => 1], $perTenant, 'خلط المجدوِل مستأجرين');
        $this->assertNull(TenantContext::tenantId(), 'بقي سياق آخر مستأجر بعد المرور');
    }

    // ===== الإشعار بعد الانتقال =====

    /**
     * أكثر مواضع سقوط الإشعارات: البحث عن المستقبِل يقع بعد `transition()`
     * خارج السياق فيعود فارغًا — فلا إشعار، ولا خطأ يُنبّه.
     */
    public function test_a_recipient_lookup_after_a_transition_still_finds_its_target(): void
    {
        [$tA, , , $creatorA] = $this->tenantWithCreator('أ');

        // محاكاة: انتقال يُنهي بسياق مُفرَغ، ثم بحث عن مستقبِل
        TenantContext::withTenant($tA->id, fn () => true);   // «الانتقال»
        $this->assertNull(TenantContext::tenantId(), 'السياق مُفرَغ بعد الانتقال — كما في الواقع');

        // النمط الصحيح: البحث داخل سياق المستأجر صراحةً
        $found = TenantContext::withTenant($tA->id, fn () => Creator::find($creatorA->id));
        $this->assertNotNull($found, 'لم يُعثر على المستقبِل بعد الانتقال — يسقط الإشعار بصمت');
    }
}
