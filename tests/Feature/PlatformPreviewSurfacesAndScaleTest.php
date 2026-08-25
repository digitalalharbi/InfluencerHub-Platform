<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\PlatformPortalEligibilityService;
use App\Domain\Platform\Support\PlatformPreviewToken;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * §P3-hardening §3/§4/§5: أيّ فعل غير آمن يُبلغ من داخل معاينة نشطة يُردّ **قبل** أي
 * ربط نموذج أو تحوّر (الحارس العالميّ يسبق SubstituteBindings)، يشمل مسارات الخروج/
 * التبديل خارج مجموعات البوّابات؛ وجلسة المالك تبقى سليمة. وبحث السياقات خادميّ مُصفَّح
 * بلا سقف ٢٥ فيبلغ المالك أيّ سياق (#26+) لا الأوّل ٢٥.
 */
class PlatformPreviewSurfacesAndScaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private function owner(): User
    {
        $u = User::create(['name' => 'Owner', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true, 'is_platform_owner' => true])->save();
        return $u;
    }

    private function clientSetup(): array
    {
        $t = Tenant::create(['name' => 'T', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        [$u, $client] = TenantContext::withBypass(function () use ($t) {
            Organization::create(['tenant_id' => $t->id, 'name' => 'Org', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'CU', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'C1', 'status' => 'active']);
            ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
            return [$u, $c];
        });
        return [$t, $u, $client];
    }

    /** §4: كل فعل غير آمن يُبلغ من معاينة نشطة يُردّ 403 قبل أي تحوّر — يشمل الخروج/التبديل. */
    public function test_every_unsafe_action_during_preview_is_blocked_before_mutation(): void
    {
        [$t, $u, $client] = $this->clientSetup();
        $owner = $this->owner();
        $token = PlatformPreviewToken::issue((int) $owner->id, (int) $u->id, (int) $t->id, 'client', (int) $client->id, null, now()->timestamp);

        // مسارات بلا ربط + مسارات ذات ربط بمعرّف وهميّ: كلّها 403 (الحارس قبل الربط، لا 404).
        $unsafe = [
            ['post', '/logout'],
            ['post', '/client/logout'],
            ['post', '/creator/logout'],
            ['post', '/partner/logout'],
            ['post', '/client/switch'],
            ['post', '/partner/switch'],
            ['post', '/creator/content'],                    // إنشاء محتوى (بلا ربط)
            ['post', '/creator/content/999999/submit'],      // ربط بمعرّف وهميّ ⇒ 403 قبل 404
            ['post', '/creator/contracts/999999/sign'],
            ['post', '/creator/notifications/read-all'],
            ['post', '/client/contracts/999999/sign'],
        ];
        foreach ($unsafe as [$method, $path]) {
            $res = $this->actingAs($owner)->{$method}("{$path}?_pv={$token}", []);
            $this->assertSame(403, $res->getStatusCode(), "لم يُحظر فعل غير آمن في المعاينة: {$method} {$path} (رمز {$res->getStatusCode()})");
        }

        // جلسة المالك سليمة: مسار مالك-فقط لا يزال 200 (لم يُنفَّذ أي متحكّم خروج).
        $this->actingAs($owner)->get('/platform')->assertSuccessful();

        // والطلب الآمن (GET) بالمعاينة لا يُحظر — الحظر يخصّ التحوّر فقط.
        $this->actingAs($owner)->get("/client?_pv={$token}")->assertSuccessful();
    }

    /** §4: طلب غير آمن بلا _pv على مسار بوّابة لا يمسّ بيانات — الحارس يفشل مغلقًا للمالك غير العضو. */
    public function test_owner_without_pv_cannot_mutate_a_portal_they_are_not_member_of(): void
    {
        [$t, $u, $client] = $this->clientSetup();
        $owner = $this->owner();
        // المالك ليس عضو عميل ⇒ EnsureClientMember يردّه (بلا _pv) — لا تحوّر ولا تسجيل خروج جلسته.
        $this->actingAs($owner)->post('/client/switch', ['client_id' => $client->id])->assertForbidden();
        $this->actingAs($owner)->get('/platform')->assertSuccessful();   // جلسة المالك سليمة
    }

    /** §5: أكثر من ٢٥ سياق عميل — كلّها قابلة للاكتشاف والمعاينة (لا سقف وظيفيّ). */
    public function test_more_than_25_client_contexts_are_discoverable_and_previewable(): void
    {
        $t = Tenant::create(['name' => 'Big', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $owner = $this->owner();

        $needleUser = null;
        $needleClient = null;
        TenantContext::withBypass(function () use ($t, &$needleUser, &$needleClient) {
            for ($i = 1; $i <= 30; $i++) {
                $u = User::create(['name' => "Ctx User {$i}", 'email' => "ctxuser{$i}@e2e.test", 'password' => bcrypt('x'), 'is_active' => true]);
                $c = Client::create(['tenant_id' => $t->id, 'client_number' => "CL-{$i}", 'display_name' => "Findable Client {$i}", 'status' => 'active']);
                ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
                if ($i === 26) {
                    $needleUser = $u;
                    $needleClient = $c;
                }
            }
        });

        $svc = app(PlatformPortalEligibilityService::class);

        // الصفحة الأولى محدودة، لكن الإجمالي ٣٠ والمزيد متاح (لا اقتصار صامت على ٢٥).
        $p1 = $svc->searchEligibleContexts($t->id, 'client', null, 1, 10);
        $this->assertSame(30, $p1['total']);
        $this->assertCount(10, $p1['items']);
        $this->assertTrue($p1['hasMore']);

        // السياق #26 يُكتشَف بالبحث بالاسم — لا يُحجب خلف سقف.
        $byName = $svc->searchEligibleContexts($t->id, 'client', 'Findable Client 26', 1, 10);
        $this->assertSame(1, $byName['total']);
        $this->assertSame((int) $needleClient->id, $byName['items'][0]['entityId']);

        // والبحث بالبريد كذلك.
        $byEmail = $svc->searchEligibleContexts($t->id, 'client', 'ctxuser26@e2e.test', 1, 10);
        $this->assertSame(1, $byEmail['total']);
        $this->assertSame((int) $needleUser->id, $byEmail['items'][0]['userId']);

        // نقطة النهاية للمالك تُرجع نفس النتيجة مع رابط بدء المعاينة.
        $this->actingAs($owner)->getJson("/platform/tenants/{$t->id}/contexts?portal=client&q=Findable+Client+26")
            ->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.entityId', (int) $needleClient->id);

        // والأهمّ: يمكن **معاينة** السياق #26 فعلًا.
        $res = $this->actingAs($owner)->get("/platform/preview/{$t->id}/client/{$needleUser->id}/{$needleClient->id}");
        $res->assertRedirect();
        $this->assertStringContainsString('/client?_pv=', $res->headers->get('Location'));
    }
}
