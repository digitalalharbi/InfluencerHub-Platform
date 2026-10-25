<?php

namespace App\Domain\Integrations\Google;

use App\Domain\Integrations\Adapters\IntegrationAdapter;
use App\Domain\Integrations\Adapters\SupportsScopedAuthorization;
use App\Domain\Integrations\Adapters\SyncResult;
use App\Domain\Integrations\Google\Services\GmailService;
use App\Domain\Integrations\Google\Services\GoogleCalendarService;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\IntegrationSyncRun;

/**
 * محوّل مساحة عمل Google (GOOGLE-01) — مزوّد واحد `google` باتّصال OAuth واحد لكل مستأجر،
 * وقدرات متعدّدة (Drive/Sheets/Gmail/Calendar) بموافقة تدريجية. يمدّد الأساس القائم بلا بنية
 * موازية: يخزّن في integration_connections (قدرات في capabilities، مؤشّرات/قنوات في meta).
 * كلّ شبكة تمرّ عبر GoogleClient (حقيقي خلف الاعتماد، مزيّف في التجريب/الاختبار). لا ادّعاء
 * حيّ قبل جولة تفويض حقيقية.
 */
class GoogleWorkspaceAdapter implements IntegrationAdapter, SupportsScopedAuthorization
{
    public const PROVIDER = 'google';

    private const AUTH_URI = 'https://accounts.google.com/o/oauth2/v2/auth';

    public function __construct(
        private GoogleClient $client,
        private ?string $clientId = null,
        private ?string $redirectUri = null,
    ) {
        $this->clientId ??= config('services.google.client_id');
        $this->redirectUri ??= config('services.google.redirect');
    }

    public function provider(): string
    {
        return self::PROVIDER;
    }

    /** @return array<int,string> */
    public function capabilities(): array
    {
        return [GoogleScopes::CAP_DRIVE, GoogleScopes::CAP_SHEETS, GoogleScopes::CAP_GMAIL, GoogleScopes::CAP_CALENDAR];
    }

    public function authorizeUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return $this->authorizeUrlScoped($state, $codeChallenge, $redirectUri, $this->capabilities());
    }

    public function authorizeUrlScoped(string $state, string $codeChallenge, string $redirectUri, array $capabilities): string
    {
        if (empty($this->clientId)) {
            throw GoogleNotConfiguredException::make();
        }
        if (! GoogleScopes::valid($capabilities)) {
            throw new \InvalidArgumentException('google_invalid_capabilities');
        }

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', GoogleScopes::forCapabilities($capabilities)),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'access_type' => 'offline',          // لإصدار refresh_token
            'prompt' => 'consent',               // ليعاد refresh_token عند إعادة التفويض
            'include_granted_scopes' => 'true',  // موافقة تدريجية
        ];

        return self::AUTH_URI.'?'.http_build_query($params);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): array
    {
        $tokens = $this->client->exchangeCode($code, $codeVerifier, $redirectUri);
        $info = $this->client->userInfo($tokens['access_token']);

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_in' => $tokens['expires_in'] ?? null,
            'scopes' => isset($tokens['scope']) ? explode(' ', (string) $tokens['scope']) : [],
            'external_account_id' => $info['sub'] ?? null,
            'external_account_name' => $info['email'] ?? null,
        ];
    }

    public function refreshTokens(IntegrationConnection $connection): array
    {
        $tokens = $this->client->refreshToken((string) $connection->refresh_token);

        return [
            'access_token' => $tokens['access_token'],
            'expires_in' => $tokens['expires_in'] ?? null,
            'scopes' => isset($tokens['scope']) ? explode(' ', (string) $tokens['scope']) : ($connection->scopes ?? []),
        ];
    }

    public function revokeTokens(IntegrationConnection $connection): bool
    {
        $token = $connection->access_token ?: $connection->refresh_token;

        return $token ? $this->client->revokeToken((string) $token) : true;
    }

    /**
     * مزامنة قدرة واحدة مجدوَلة/تدريجية. Gmail (سجل History) وCalendar (رموز المزامنة) فقط —
     * Drive/Sheets طلب عند الحاجة لا مزامنة دورية. قدرة غير مدعومة للمزامنة ⇒ نتيجة فارغة صريحة.
     */
    public function sync(IntegrationConnection $connection, IntegrationSyncRun $run): SyncResult
    {
        return match ($run->capability) {
            GoogleScopes::CAP_GMAIL => app(GmailService::class)->syncIncremental($connection),
            GoogleScopes::CAP_CALENDAR => app(GoogleCalendarService::class)->syncIncremental($connection),
            default => new SyncResult(fetched: 0),
        };
    }
}
