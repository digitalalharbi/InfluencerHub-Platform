<?php

namespace App\Domain\Integrations\Google;

/**
 * خرائط نطاقات OAuth لمساحة عمل Google، لكل قدرة. نطلب الحدّ الأدنى اللازم لكل قدرة
 * (least privilege) ونجمعها تدريجيًّا (incremental) للقدرات المطلوبة فقط. القيم هي نطاقات
 * Google الرسمية الحقيقية — لا نخترع نطاقًا؛ ما يحتاج موافقة شاشة الموافقة مُوثَّق في
 * docs/EXTERNAL_BLOCKERS.md.
 */
final class GoogleScopes
{
    /** قدرات مساحة العمل التي يدعمها المحوّل. */
    public const CAP_DRIVE = 'google_drive';

    public const CAP_SHEETS = 'google_sheets';

    public const CAP_GMAIL = 'gmail';

    public const CAP_CALENDAR = 'google_calendar';

    public const CAP_PEOPLE = 'google_people';

    public const ALL_CAPABILITIES = [
        self::CAP_DRIVE, self::CAP_SHEETS, self::CAP_GMAIL, self::CAP_CALENDAR, self::CAP_PEOPLE,
    ];

    /** نطاقات هوية دائمًا (لاكتشاف الحساب المتّصل — email/openid). */
    public const IDENTITY = [
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];

    /** @var array<string,array<int,string>> نطاق كل قدرة (أقلّ امتياز ممكن). */
    public const BY_CAPABILITY = [
        self::CAP_DRIVE => [
            'https://www.googleapis.com/auth/drive.file',      // الملفات التي ينشئها/يفتحها التطبيق فقط
            'https://www.googleapis.com/auth/drive.metadata.readonly',
        ],
        self::CAP_SHEETS => [
            'https://www.googleapis.com/auth/spreadsheets',
        ],
        self::CAP_GMAIL => [
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
        ],
        self::CAP_CALENDAR => [
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events',
        ],
        self::CAP_PEOPLE => [
            'https://www.googleapis.com/auth/contacts.readonly',
        ],
    ];

    /**
     * يحلّ مجموعة النطاقات لقدرات مطلوبة (هوية + نطاق كل قدرة)، مُزالة التكرار ومرتّبة.
     *
     * @param  array<int,string>  $capabilities
     * @return array<int,string>
     */
    public static function forCapabilities(array $capabilities): array
    {
        $scopes = self::IDENTITY;
        foreach ($capabilities as $cap) {
            foreach (self::BY_CAPABILITY[$cap] ?? [] as $scope) {
                $scopes[] = $scope;
            }
        }
        $scopes = array_values(array_unique($scopes));
        sort($scopes);

        return $scopes;
    }

    /** @param array<int,string> $capabilities */
    public static function valid(array $capabilities): bool
    {
        foreach ($capabilities as $cap) {
            if (! in_array($cap, self::ALL_CAPABILITIES, true)) {
                return false;
            }
        }

        return $capabilities !== [];
    }
}
