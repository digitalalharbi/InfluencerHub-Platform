<?php

namespace App\Domain\Integrations\Adapters;

use App\Domain\Integrations\Models\IntegrationConnection;

/**
 * قدرة OAuth اختيارية لمحوّل تكامل (INTEGRATIONS-01 · §5). المحوّلات التي تدعم OAuth تنفّذها؛
 * OAuthFlowService يقود الدورة محايدًا للمزوّد (state + PKCE + تبادل + تحديث + إبطال).
 * تُعيد الدوالّ مصفوفة رموز: ['access_token','refresh_token'?, 'expires_in'?, 'scopes'?, 'external_account_id'?, 'external_account_name'?].
 */
interface SupportsOAuth
{
    /** رابط تفويض المزوّد (مع state وتحدّي PKCE حين يدعمه المزوّد). */
    public function authorizeUrl(string $state, string $codeChallenge, string $redirectUri): string;

    /** يبادل رمز التفويض برموز وصول (مع مُتحقّق PKCE). @return array<string,mixed> */
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): array;

    /** يحدّث رموز اتصال منتهٍ باستخدام refresh_token. @return array<string,mixed> */
    public function refreshTokens(IntegrationConnection $connection): array;

    /** يُبطل رموز الاتصال لدى المزوّد (best-effort). */
    public function revokeTokens(IntegrationConnection $connection): bool;
}
