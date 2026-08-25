<?php

namespace App\Http\Portal;

use App\Domain\Identity\Models\User;

/**
 * حلّال سياق بوّابة — تعريف واحد للحلّ يستعمله الحارس العاديّ والمعاينة معًا
 * (§P3-hardening §3: لا منطق مكرّر في موضعين).
 */
interface PortalContextResolver
{
    /**
     * يحلّ سياق البوّابة لمستخدم وكيان مختار. يعيد null إن لم يكن مؤهَّلًا.
     *
     * @param  User      $user      المستخدم الحقيقي للبوّابة (الهدف في المعاينة).
     * @param  int|null  $entityId  الكيان المختار (client/agency id…)؛ في الوضع العاديّ
     *                              تفضيل الجلسة (قد يكون null → أوّل عضوية).
     * @param  bool      $exact     true (معاينة): يجب مطابقة الكيان تمامًا وإلا null —
     *                              لا سقوط إلى «الأوّل». false (عاديّ): يسمح بالسقوط.
     */
    public function resolve(User $user, ?int $entityId, bool $exact): ?PortalContext;
}
