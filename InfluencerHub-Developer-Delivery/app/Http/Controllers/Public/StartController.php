<?php

namespace App\Http\Controllers\Public;

use App\Domain\Brands\Services\BrandSignupService;
use App\Domain\Onboarding\Services\SelfSignupService;
use App\Domain\Onboarding\Support\AccountTypes;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/start` — المسار الرسمي **الوحيد** لاختيار نوع الحساب وبدء التسجيل.
 *
 * ## لماذا وُحِّد
 *
 * كان الاختيار يقع في ثلاثة أماكن: `/` و`/register` و`/register/account-type`،
 * ولكلٍّ نسخته من النصّ ومن الروابط. فاختلفت الصياغات، وأشار أحدها إلى
 * `/register/client` وهو مسار لا يُنشئ مساحة علامة. `/register` و
 * `/register/account-type` صارا تحويلًا إلى هنا، والمحتوى من `AccountTypes`.
 *
 * ## المعاملات لا تضيع
 *
 * `type` و`email` و`referral` و`plan` وأيّ معامل آخر تُحمَل مع التحويل ثم
 * تُمرَّر إلى مسار النوع. رابطُ حملةٍ إعلانية يحمل `?plan=pro&referral=x`
 * يجب أن يصل إلى نهاية التسجيل، وإلّا ضاع مصدر العميل وخطّته.
 *
 * ## البريد يُدخَل مرّة
 *
 * يُجمع هنا ثم يُسلَّم إلى تدفّق النوع مع رمزه — فلا يُطلب مرّتين.
 *
 * > **حدٌّ معروف:** تدفّق العلامة يتحقّق من البريد **والجوال** قبل نموذج
 * > التسجيل. وتدفّقا الوكالة وصانع المحتوى يتحقّقان من البريد وحده؛ توحيد
 * > التحقّق بالجوال لهما يقتضي دمج جدولَي التسجيل، ولم يُنفَّذ بعد.
 */
class StartController extends Controller
{
    /** المعاملات التي تُحمَل عبر التحويل — ما عداها يُهمَل فلا يصير الرابط نفقًا مفتوحًا. */
    public const CARRIED = ['type', 'email', 'referral', 'plan', 'utm_source', 'utm_medium', 'utm_campaign'];

    public function __construct(
        private BrandSignupService $brandSignups,
        private SelfSignupService $selfSignups,
    ) {}

    public function index(Request $r): Response|RedirectResponse
    {
        // من يعمل بالفعل لا يُعرض عليه تسجيل — ويُرسَل إلى مساحته
        if ($redirect = app(SiteController::class)->portalRedirect($r)) {
            return $redirect;
        }

        $type = $r->query('type');

        return Inertia::render('Public/Start', [
            'accountTypes' => AccountTypes::all(),
            'selected' => AccountTypes::isValid($type) ? $type : null,
            'prefill' => [
                'email' => $r->query('email'),
            ],
            'carry' => $this->carried($r),
        ]);
    }

    /**
     * يبدأ التسجيل للنوع المختار.
     *
     * البريد يُنشأ به سجلّ التدفّق المناسب، ويُسلَّم رمزه، ثم يُحوَّل المستخدم
     * إلى خطوة التحقّق في مسار نوعه. فلا يُدخَل البريد مرّتين ولا يضيع النوع.
     */
    public function begin(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'type' => 'required|string|in:'.implode(',', AccountTypes::KEYS),
            'email' => 'required|email|max:190',
        ], [], ['type' => 'نوع الحساب', 'email' => 'البريد الإلكتروني']);

        $carry = $this->carried($r, except: ['type', 'email']);

        return match ($data['type']) {
            AccountTypes::BRAND => $this->beginBrand($data['email'], $r->ip(), $carry),
            AccountTypes::AGENCY => $this->beginAgency($data['email'], $r->ip(), $carry),
            default => redirect($this->withQuery('/join/creator', $carry + ['email' => $data['email']])),
        };
    }

    // ===== لكل نوع مدخله =====

    private function beginBrand(string $email, ?string $ip, array $carry): RedirectResponse
    {
        [$signup, $code] = $this->brandSignups->start($email, $ip);
        $this->deliver($email, $code);

        return redirect($this->withQuery("/register/brand/verify/{$signup->reference}", $carry));
    }

    private function beginAgency(string $email, ?string $ip, array $carry): RedirectResponse
    {
        [$signup, $code] = $this->selfSignups->start($email, AccountTypes::AGENCY, $ip);
        $this->deliver($email, $code);

        return redirect($this->withQuery("/register/agency/verify/{$signup->reference}", $carry));
    }

    // ===== أدوات =====

    /**
     * المعاملات المحمولة.
     *
     * @return array<string,string>
     */
    private function carried(Request $r, array $except = []): array
    {
        // `all()` لا `query()`: عند الإرسال (POST) تصل المعاملات في **الجسم**
        // لا في الرابط. وقراءة الرابط وحده كانت تُسقط `referral` و`plan` عند
        // أوّل إرسال — أي في الخطوة التي يبدأ فيها التسجيل فعلًا.
        return collect($r->all())
            ->only(array_diff(self::CARRIED, $except))
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->all();
    }

    /** @param array<string,string> $query */
    private function withQuery(string $path, array $query): string
    {
        return $query === [] ? $path : $path.'?'.http_build_query($query);
    }

    private function deliver(string $email, string $code): void
    {
        Mail::raw(
            "رمز تأكيد بريدك في إنفلونسر هَب: {$code}\nينتهي خلال 15 دقيقة.",
            fn ($m) => $m->to($email)->subject('رمز تأكيد البريد — إنفلونسر هَب'),
        );

        if (! app()->environment('production')) {
            Log::info("[start] رمز التحقّق لـ{$email}: {$code}");
        }
    }
}
