<?php

namespace App\Http\Portal;

/**
 * نتيجة حلّ سياق بوّابة — مصدر واحد يستهلكه الحارس العاديّ **و**معاينة مالك المنصّة،
 * فلا يتباعد ما يراه المستخدم الحقيقي عمّا يراه المعايِن (§P3-hardening §3).
 *
 * - `attributes`: خصائص الطلب التي تقرؤها متحكّمات البوّابة (activeClient/clientMembership…).
 * - `share`: بيانات `view()->share` لواجهات Blade (myClients/clientUnread…).
 * - `sessionKey`/`sessionValue`: مفتاح الجلسة الذي يكتبه **الوضع العاديّ فقط**؛
 *   المعاينة تتجاهله عمدًا (عزل متعدّد النوافذ — لا كتابة جلسة).
 */
final class PortalContext
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?int $organizationId,
        /** @var array<string,mixed> */
        public readonly array $attributes,
        /** @var array<string,mixed> */
        public readonly array $share = [],
        public readonly ?string $sessionKey = null,
        public readonly ?int $sessionValue = null,
    ) {
    }
}
