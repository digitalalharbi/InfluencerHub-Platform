<?php

namespace App\Domain\Automation\Actions;

/**
 * إجراء أتمتة. لا يُسمَح لأيّ إجراء باعتماد مال/عقد أو تغيير حالة مالية —
 * فقط إشعار/مهمة/تصعيد/تذكير/تدقيق. التنفيذ يعيد وصفًا للنتيجة للتسجيل.
 */
interface AutomationAction
{
    public function type(): string;

    /**
     * @param  array<string,mixed>  $config  إعداد الإجراء من القاعدة
     * @param  array<string,mixed>  $context  سياق المحفّز
     * @return array<string,mixed> نتيجة قابلة للتسجيل
     */
    public function execute(array $config, array $context, int $tenantId): array;
}
