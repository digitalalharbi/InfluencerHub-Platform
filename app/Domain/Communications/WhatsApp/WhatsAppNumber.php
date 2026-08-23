<?php

namespace App\Domain\Communications\WhatsApp;

/**
 * تطبيع رقم واتساب إلى صيغة Cloud API (أرقام فقط بلا +، مثل 9665XXXXXXXX).
 * لا نُخمّن أرقامًا غير صالحة — نُعيد null فيُسجَّل التسليم skipped بدل إرسال خاطئ.
 * يدعم الأرقام السعودية المحلية والدولية والصيغة الدولية العامة.
 */
final class WhatsAppNumber
{
    public static function normalize(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $s = trim($raw);
        // 00 بادئة دولية → بلا بادئة
        if (str_starts_with($s, '00')) {
            $s = substr($s, 2);
        }
        $s = ltrim($s, '+');
        $digits = preg_replace('/\D+/', '', $s) ?? '';

        if ($digits === '') {
            return null;
        }

        // سعودي محلي: 05XXXXXXXX (10) أو 5XXXXXXXX (9) → 9665XXXXXXXX
        if (preg_match('/^05\d{8}$/', $digits)) {
            return '966' . substr($digits, 1);
        }
        if (preg_match('/^5\d{8}$/', $digits)) {
            return '966' . $digits;
        }
        // سعودي دولي مكتمل: 9665XXXXXXXX (12)
        if (preg_match('/^9665\d{8}$/', $digits)) {
            return $digits;
        }
        // دولي عام معقول: 8–15 رقمًا لا يبدأ بصفر
        if (preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            return $digits;
        }

        return null;
    }
}
