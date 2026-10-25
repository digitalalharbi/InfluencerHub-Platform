<?php

namespace App\Domain\Integrations\Adapters;

/**
 * قدرة اختيارية فوق SupportsOAuth: محوّل يبني رابط تفويض بنطاقات تدريجية حسب القدرات
 * المطلوبة (incremental consent)، لا نطاقات كاملة دائمًا. يستعمله OAuthFlowService حين
 * تُمرَّر قدرات لـbegin()؛ المحوّلات التي لا تنفّذه تبقى على authorizeUrl الكامل.
 */
interface SupportsScopedAuthorization extends SupportsOAuth
{
    /**
     * رابط تفويض لقدرات محدّدة فقط (أقلّ امتياز).
     *
     * @param  array<int,string>  $capabilities
     */
    public function authorizeUrlScoped(string $state, string $codeChallenge, string $redirectUri, array $capabilities): string;
}
