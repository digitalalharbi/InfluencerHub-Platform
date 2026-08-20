<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Models\PoolRecommendation;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** ترشيحات المؤثرين في بوابة العميل — قبول/رفض، معزول على العميل النشِط. */
class ClientRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Client,2:Tenant} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'شركة', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$u, $client, $t];
    }

    private function rec(Tenant $t, Client $client, array $over = []): PoolRecommendation
    {
        return PoolRecommendation::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $t->id, 'client_id' => $client->id,
            'name' => 'مؤثر', 'platform' => 'snapchat', 'followers' => 500000,
            'categories' => ['عناية'], 'price_minor' => 1500000, 'source_type' => 'celebrity',
            'status' => 'recommended',
        ], $over));
    }

    public function test_list_renders_recommendations(): void
    {
        [$u, $client, $t] = $this->world();
        $this->rec($t, $client);

        $this->actingAs($u)->get('/beta/client/recommendations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Recommendations/Index')
                ->where('counts.pending', 1)
                ->where('counts.total', 1)
                ->has('items.data', 1));
    }

    public function test_client_never_sees_phone(): void
    {
        [$u, $client, $t] = $this->world();
        $this->rec($t, $client);
        // نتأكّد أن الحمولة لا تحوي أي مفتاح جوّال إطلاقًا
        $this->actingAs($u)->get('/beta/client/recommendations')
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.data.0', fn (Assert $row) => $row
                    ->missing('phone')->missing('costPost')->missing('costCoverage')
                    ->hasAll(['id', 'name', 'platformLabel', 'followers', 'priceMinor', 'status'])
                    ->etc()));
    }

    public function test_approve_sets_status_and_timestamp(): void
    {
        [$u, $client, $t] = $this->world();
        $r = $this->rec($t, $client);

        $this->actingAs($u)->post("/beta/client/recommendations/{$r->id}/decision", ['decision' => 'approved'])
            ->assertRedirect();

        $r->refresh();
        $this->assertSame('approved', $r->status);
        $this->assertNotNull($r->decided_at);
    }

    public function test_reject_stores_reason(): void
    {
        [$u, $client, $t] = $this->world();
        $r = $this->rec($t, $client);

        $this->actingAs($u)->post("/beta/client/recommendations/{$r->id}/decision", ['decision' => 'rejected', 'reason' => 'خارج الفئة'])
            ->assertRedirect();

        $r->refresh();
        $this->assertSame('rejected', $r->status);
        $this->assertSame('خارج الفئة', $r->decision_reason);
    }

    public function test_invalid_decision_is_rejected(): void
    {
        [$u, $client, $t] = $this->world();
        $r = $this->rec($t, $client);

        $this->actingAs($u)->post("/beta/client/recommendations/{$r->id}/decision", ['decision' => 'maybe'])
            ->assertSessionHasErrors('decision');
    }

    public function test_cannot_decide_another_clients_recommendation(): void
    {
        [$u, , ] = $this->world();
        [, $otherClient, $otherTenant] = $this->world();
        $foreign = $this->rec($otherTenant, $otherClient);

        // المستخدم الأول لا يملك توصية العميل الآخر
        $this->actingAs($u)->post("/beta/client/recommendations/{$foreign->id}/decision", ['decision' => 'approved'])
            ->assertNotFound();

        $foreign->refresh();
        $this->assertSame('recommended', $foreign->status);
    }
}
