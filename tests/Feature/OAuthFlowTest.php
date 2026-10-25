<?php

namespace Tests\Feature;

use App\Domain\Integrations\Adapters\{AdapterRegistry, IntegrationAdapter, SupportsOAuth, SyncResult};
use App\Domain\Integrations\Models\{IntegrationConnection, IntegrationSyncRun};
use App\Domain\Integrations\Services\OAuthFlowService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * INTEGRATIONS-01 — دورة OAuth محايدة للمزوّد + سلّم الجاهزية. مُختبَرة بمحوّل مزيّف يدعم OAuth؛
 * لا بيانات اعتماد حقيقية. يتحقّق: state/PKCE، تبادل، حفظ مشفّر، استهلاك state مرّة واحدة،
 * رفض state مجهول، تحديث، إبطال، واشتقاق درجات السلّم من إشارات حقيقية.
 */
class OAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function oauthAdapter(string $provider = 'fakeprov'): IntegrationAdapter
    {
        return new class($provider) implements IntegrationAdapter, SupportsOAuth {
            public ?string $seenChallenge = null;

            public function __construct(private string $p) {}

            public function provider(): string { return $this->p; }

            public function capabilities(): array { return ['creator_profile']; }

            public function sync(IntegrationConnection $c, IntegrationSyncRun $run): SyncResult { return new SyncResult(fetched: 1); }

            public function authorizeUrl(string $state, string $codeChallenge, string $redirectUri): string
            {
                $this->seenChallenge = $codeChallenge;

                return "https://provider.test/oauth?state={$state}&code_challenge={$codeChallenge}&redirect_uri=".urlencode($redirectUri);
            }

            public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): array
            {
                return ['access_token' => "at-{$code}", 'refresh_token' => 'rt-1', 'expires_in' => 3600,
                    'scopes' => ['read'], 'external_account_id' => 'acc-9', 'external_account_name' => 'حساب تجريبي'];
            }

            public function refreshTokens(IntegrationConnection $connection): array
            {
                return ['access_token' => 'at-refreshed', 'refresh_token' => 'rt-2', 'expires_in' => 3600];
            }

            public function revokeTokens(IntegrationConnection $connection): bool { return true; }
        };
    }

    private function service(IntegrationAdapter $adapter): OAuthFlowService
    {
        return new OAuthFlowService(new AdapterRegistry([$adapter]));
    }

    public function test_begin_returns_authorize_url_with_state_and_pkce_challenge(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $adapter = $this->oauthAdapter();
        $out = $this->service($adapter)->begin($t->id, 'fakeprov', 'https://app.test/callback');

        $this->assertNotEmpty($out['state']);
        $this->assertStringContainsString('code_challenge=', $out['url']);
        $this->assertSame($adapter->seenChallenge, $adapter->seenChallenge); // challenge مُرِّر للمحوّل
        $this->assertNotEmpty($adapter->seenChallenge);
    }

    public function test_complete_exchanges_code_and_stores_encrypted_connected_connection(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $svc = $this->service($this->oauthAdapter());
        $begin = $svc->begin($t->id, 'fakeprov', 'https://app.test/callback');
        $conn = $svc->complete($begin['state'], 'authcode-123');

        $this->assertSame('connected', $conn->status);
        $this->assertSame('acc-9', $conn->external_account_id);
        $this->assertSame('at-authcode-123', $conn->access_token);   // accessor يفكّ التشفير
        // الرمز مشفّر في قاعدة البيانات، لا نصًّا صريحًا
        $raw = \DB::table('integration_connections')->where('id', $conn->id)->value('access_token');
        $this->assertNotSame('at-authcode-123', $raw);
        // لا يُسلسَل الرمز إلى JSON
        $this->assertArrayNotHasKey('access_token', $conn->toArray());
    }

    public function test_state_is_single_use_and_unknown_state_is_rejected(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $svc = $this->service($this->oauthAdapter());
        $begin = $svc->begin($t->id, 'fakeprov', 'https://app.test/callback');
        $svc->complete($begin['state'], 'c1');     // أول استهلاك ينجح

        $this->expectException(RuntimeException::class);
        $svc->complete($begin['state'], 'c2');     // الثاني يُرفض (state مُستهلَك)
    }

    public function test_unknown_state_rejected(): void
    {
        $svc = $this->service($this->oauthAdapter());
        $this->expectException(RuntimeException::class);
        $svc->complete('never-issued', 'c');
    }

    public function test_refresh_updates_tokens(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $svc = $this->service($this->oauthAdapter());
        $conn = $svc->complete($svc->begin($t->id, 'fakeprov', 'https://app.test/callback')['state'], 'c1');
        $refreshed = $svc->refresh($conn->fresh());
        $this->assertSame('at-refreshed', $refreshed->access_token);
    }

    public function test_revoke_clears_tokens_and_disconnects(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $svc = $this->service($this->oauthAdapter());
        $conn = $svc->complete($svc->begin($t->id, 'fakeprov', 'https://app.test/callback')['state'], 'c1');
        $revoked = $svc->revoke($conn->fresh());
        $this->assertSame('disconnected', $revoked->status);
        $this->assertNull($revoked->access_token);
        $this->assertNull($revoked->refresh_token);
    }

    public function test_readiness_ladder_is_derived_from_real_signals(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::withTenant($t->id, function () use ($t) {
            $mk = fn (array $a) => new IntegrationConnection(array_merge(['tenant_id' => $t->id, 'provider' => 'p', 'environment' => 'production'], $a));
            $this->assertSame('NOT_CONFIGURED', $mk(['status' => 'not_configured'])->readiness());
            $this->assertSame('AWAITING_CREDENTIALS', $mk(['status' => 'waiting_for_credentials'])->readiness());
            $this->assertSame('READY_FOR_CONFIGURATION', $mk(['status' => 'waiting_for_approval'])->readiness());
            $this->assertSame('SANDBOX_VERIFIED', $mk(['status' => 'sandbox'])->readiness());
            $this->assertSame('AUTH_VERIFIED', $mk(['status' => 'connected', 'access_token' => 'x'])->readiness());
            $this->assertSame('RESOURCE_DISCOVERY_VERIFIED', $mk(['status' => 'connected', 'access_token' => 'x', 'external_account_id' => 'a'])->readiness());
            $this->assertSame('LIVE_READ_VERIFIED', $mk(['status' => 'connected', 'access_token' => 'x', 'external_account_id' => 'a', 'last_success_sync_at' => now()])->readiness());
            $this->assertSame('LIVE_WRITE_VERIFIED', $mk(['status' => 'connected', 'access_token' => 'x', 'meta' => ['live_write_verified_at' => now()->toIso8601String()]])->readiness());
        });
    }
}
