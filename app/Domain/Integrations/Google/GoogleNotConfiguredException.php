<?php

namespace App\Domain\Integrations\Google;

use RuntimeException;

/**
 * يُرمى حين تُطلب عملية Google حقيقية بلا بيانات اعتماد (Client ID/Secret).
 * الإطار كامل داخليًّا؛ التشغيل الحيّ محجوب خارجيًّا (BLOCKED_EXTERNAL_CREDENTIALS).
 * لا نختلق نجاحًا أبدًا: غياب الاعتماد = خطأ صريح، لا بيانات وهمية.
 */
class GoogleNotConfiguredException extends RuntimeException
{
    public static function make(): self
    {
        return new self('google_not_configured: set GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_REDIRECT_URI');
    }
}
