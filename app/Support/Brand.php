<?php

namespace App\Support;

/**
 * الهوية الرسمية لمنتج InfluencerHub — مصدر وصول واحد لكل واجهة/مستند/بريد.
 * (اسم المنتج وموقعه ونطاقه معلومة؛ لا كيان قانوني/علامة مسجّلة مُثبَتة، فلا يُدّعى ذلك.)
 * لا يُكتب الاسم أو النطاق يدويًّا في أيّ مكان؛ يُقرأ من هنا (config/influencerhub.php).
 * ملاحظة مهمّة: هذه هوية المنصّة. هوية المستأجر (الوكالة) تبقى صاحبة المستندات
 * المالية/القانونية؛ InfluencerHub تظهر بوصفها المزوّد فقط («Powered by»).
 */
class Brand
{
    public static function name(): string
    {
        return (string) config('influencerhub.product_name', 'InfluencerHub');
    }

    public static function tagline(): string
    {
        return (string) config('influencerhub.tagline', 'منصة إدارة حملات المؤثرين وصناع المحتوى');
    }

    /** رابط المنتج العام الحقيقي (بلا شرطة نهائية). */
    public static function url(): string
    {
        return rtrim((string) config('influencerhub.url', 'https://influencerhub.io'), '/');
    }

    public static function domain(): string
    {
        return (string) config('influencerhub.domain', 'influencerhub.io');
    }

    public static function infoUrl(): string
    {
        return self::url() . (string) config('influencerhub.info_path', '/info');
    }

    public static function privacyUrl(): string
    {
        return self::url() . (string) config('influencerhub.privacy_path', '/privacy');
    }

    public static function termsUrl(): string
    {
        return self::url() . (string) config('influencerhub.terms_path', '/terms');
    }

    public static function helpUrl(): string
    {
        return self::url() . (string) config('influencerhub.help_path', '/help');
    }

    /** بريد التواصل العام الحقيقي (يختلف عن مُرسِل البريد الآليّ no-reply@). */
    public static function publicEmail(): string
    {
        return (string) config('influencerhub.public_email', 'info@influencerhub.io');
    }

    /** الهاتف العام — القيمة المخزّنة الأصلية (بلا تنسيق). */
    public static function publicPhone(): string
    {
        return (string) config('influencerhub.public_phone', '+966550137003');
    }

    /** عرض الهاتف بتجميع مقروء (لا يغيّر القيمة المخزّنة). */
    public static function publicPhoneDisplay(): string
    {
        $p = self::publicPhone();
        // +966550137003 → +966 55 013 7003
        if (preg_match('/^\+966(\d{2})(\d{3})(\d{4})$/', $p, $m)) {
            return "+966 {$m[1]} {$m[2]} {$m[3]}";
        }
        return $p;
    }

    public static function mailFromAddress(): string
    {
        return (string) config('influencerhub.mail.from_address', 'no-reply@influencerhub.io');
    }

    public static function mailFromName(): string
    {
        return (string) config('influencerhub.mail.from_name', self::name());
    }

    /** بريد دعم مُتحقَّق أو null — لا نخترع صندوقًا عاملًا. */
    public static function supportEmail(): ?string
    {
        $e = config('influencerhub.support.email');
        return $e ? (string) $e : null;
    }

    /** تذييل المستندات الموحّد. */
    public static function documentFooter(): string
    {
        return 'تم إنشاء هذا المستند عبر ' . self::name() . ' · ' . self::domain();
    }

    /**
     * اسم ملفّ مُصدَّر مُعرَّف بالعلامة وآمن: InfluencerHub-<رمز/عنوان>.<امتداد>.
     * يُستخرج الرمز اللاتيني من العنوان (CM-1-2 / INV-1-0001) إن وُجد، وإلّا slug.
     */
    public static function documentFilename(string $title, string $ext = 'pdf'): string
    {
        $token = '';
        if (preg_match('/[A-Za-z]{2,}[-\d]*\d/', $title, $m)) {
            $token = $m[0];                                   // رمز المستند اللاتيني (رقمه)
        }
        $token = $token !== '' ? $token : \Illuminate\Support\Str::slug($title);
        $token = trim($token, '-') ?: 'document';

        return self::name() . '-' . $token . '.' . $ext;
    }
}
