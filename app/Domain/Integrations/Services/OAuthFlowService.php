<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Adapters\AdapterRegistry;
use App\Domain\Integrations\Adapters\SupportsOAuth;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * دورة OAuth محايدة للمزوّد (INTEGRATIONS-01 · §5): بدء (state + PKCE) → عودة (تحقّق state +
 * تبادل الرمز) → تحديث → إبطال. يخزّن الرموز مشفّرة عبر IntegrationConnection. لا يثق بـstate
 * غير مُخزَّن (منع تزوير CSRF/replay)، وينتهي state بعد 10 دقائق ويُستهلك مرّة واحدة.
 * المزوّدات الحقيقية تحتاج بيانات اعتماد (BLOCKED_EXTERNAL_CREDENTIALS)؛ الدورة مُختبَرة بمحوّل مزيّف.
 */
class OAuthFlowService
{
    private const STATE_TTL = 600; // ثوانٍ

    public function __construct(private AdapterRegistry $adapters) {}

    /**
     * يبدأ الدورة: يولّد state + PKCE، يخزّنهما، ويعيد رابط التفويض.
     *
     * @return array{url:string,state:string}
     */
    public function begin(int $tenantId, string $provider, string $redirectUri): array
    {
        $adapter = $this->oauthAdapter($provider);
        $state = Str::random(40);
        $verifier = self::base64Url(random_bytes(32));
        $challenge = self::base64Url(hash('sha256', $verifier, true));

        Cache::put(self::key($state), [
            'tenant_id' => $tenantId, 'provider' => $provider, 'verifier' => $verifier, 'redirect_uri' => $redirectUri,
        ], self::STATE_TTL);

        return ['url' => $adapter->authorizeUrl($state, $challenge, $redirectUri), 'state' => $state];
    }

    /** يُكمل الدورة من ردّ المزوّد: يتحقّق من state، يبادل الرمز، ويحفظ الاتصال المتّصل. */
    public function complete(string $state, string $code): IntegrationConnection
    {
        $ctx = Cache::pull(self::key($state));   // استهلاك مرّة واحدة
        if (! is_array($ctx)) {
            throw new RuntimeException('oauth_state_invalid');
        }
        $adapter = $this->oauthAdapter($ctx['provider']);
        $tokens = $adapter->exchangeCode($code, $ctx['verifier'], $ctx['redirect_uri']);

        return TenantContext::withTenant($ctx['tenant_id'], function () use ($ctx, $tokens) {
            $conn = IntegrationConnection::firstOrNew([
                'tenant_id' => $ctx['tenant_id'], 'provider' => $ctx['provider'], 'environment' => 'production',
            ]);
            $conn->fill($this->tokenAttrs($tokens) + [
                'status' => 'connected', 'health' => 'healthy', 'connected_at' => now(),
                'external_account_id' => $tokens['external_account_id'] ?? $conn->external_account_id,
                'external_account_name' => $tokens['external_account_name'] ?? $conn->external_account_name,
            ]);
            $conn->save();

            return $conn;
        });
    }

    /** يحدّث رموز اتصال (قبل/بعد الانتهاء) عبر refresh_token. */
    public function refresh(IntegrationConnection $conn): IntegrationConnection
    {
        $adapter = $this->oauthAdapter($conn->provider);
        if (empty($conn->refresh_token)) {
            throw new RuntimeException('no_refresh_token');
        }
        $tokens = $adapter->refreshTokens($conn);
        $conn->fill($this->tokenAttrs($tokens) + ['status' => 'connected', 'health' => 'healthy']);
        $conn->save();

        return $conn;
    }

    /** يُبطل الرموز لدى المزوّد (best-effort) ثم يفصل الاتصال محليًّا. */
    public function revoke(IntegrationConnection $conn): IntegrationConnection
    {
        $adapter = $this->oauthAdapter($conn->provider);
        try {
            $adapter->revokeTokens($conn);
        } catch (\Throwable) {
            // الإبطال لدى المزوّد best-effort؛ الفصل المحلّي يحدث دائمًا
        }
        $conn->fill(['status' => 'disconnected', 'health' => 'unknown',
            'access_token' => null, 'refresh_token' => null, 'token_expires_at' => null, 'disconnected_at' => now()]);
        $conn->save();

        return $conn;
    }

    /** @param array<string,mixed> $tokens @return array<string,mixed> */
    private function tokenAttrs(array $tokens): array
    {
        $attrs = ['access_token' => $tokens['access_token'] ?? null];
        if (array_key_exists('refresh_token', $tokens)) {
            $attrs['refresh_token'] = $tokens['refresh_token'];
        }
        if (! empty($tokens['scopes'])) {
            $attrs['scopes'] = (array) $tokens['scopes'];
        }
        if (! empty($tokens['expires_in'])) {
            $attrs['token_expires_at'] = now()->addSeconds((int) $tokens['expires_in']);
        }

        return $attrs;
    }

    private function oauthAdapter(string $provider): SupportsOAuth
    {
        $adapter = $this->adapters->get($provider);
        if (! $adapter instanceof SupportsOAuth) {
            throw new RuntimeException("provider_oauth_unsupported:{$provider}");
        }

        return $adapter;
    }

    private static function key(string $state): string
    {
        return 'oauth_state:'.$state;
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
