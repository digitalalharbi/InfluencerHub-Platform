<?php
namespace App\Domain\Creators\Support;
use Illuminate\Support\Facades\Crypt;
/** تشفير فعلي للبيانات المالية الحساسة (IBAN). لا يُخزَّن الرقم الخام؛ يُعرض آخر 4 فقط. */
final class FinancialCrypto {
    public static function encryptIban(string $iban): array {
        $clean = preg_replace('/\s+/', '', strtoupper($iban));
        return ['iban_encrypted' => Crypt::encryptString($clean), 'iban_last4' => substr($clean, -4)];
    }
    public static function decryptIban(?string $encrypted): ?string {
        if (! $encrypted) return null;
        try { return Crypt::decryptString($encrypted); } catch (\Throwable) { return null; }
    }
    public static function masked(?string $last4): string {
        return $last4 ? '•••• •••• •••• ' . $last4 : '—';
    }
}
