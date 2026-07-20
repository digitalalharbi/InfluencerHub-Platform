<?php

namespace App\Http\Controllers\Public;

use App\Domain\Brands\Models\BrandClaimRequest;
use App\Domain\Brands\Models\BrandSignup;
use App\Domain\Brands\Services\BrandClaimService;
use App\Domain\Brands\Services\BrandMatchingService;
use App\Domain\Brands\Services\BrandProvisioningService;
use App\Domain\Brands\Services\BrandSignupService;
use App\Domain\CRM\Models\Brand;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تسجيل العلامة التجارية لنفسها.
 *
 * ## ما لا يُعاد إلى المتصفّح أبدًا
 *
 * نتيجة المطابقة. لا اسم العلامة المرشَّحة، ولا مستأجرها، ولا حتّى **وجودها**.
 * الردّ بعد خطوة البيانات واحدٌ في كل الأحوال — سواء وُجد مرشَّح قويّ أو لم
 * يوجد شيء. غير ذلك يجعل البوّابة أداة تعداد: يجرّب المهاجم نطاقات وأسماء
 * فيعرف من هم عملاؤنا.
 *
 * ولذلك المسار بعد المطابقة **لا يتفرّع في الواجهة**: الخادم يقرّر أيّ صفحة
 * تُعرض، والصفحتان تبدآن بالجملة نفسها.
 */
class BrandSignupController extends Controller
{
    public function __construct(
        private BrandSignupService $svc,
        private BrandMatchingService $matcher,
    ) {}

    // ===== 1) البريد =====

    public function startForm(): Response
    {
        return Inertia::render('Public/BrandSignup/Start');
    }

    public function start(Request $r): RedirectResponse
    {
        $data = $r->validate(
            ['email' => 'required|email|max:190'],
            [],
            ['email' => 'البريد الإلكتروني'],
        );

        // لا يُفحص وجود بريد سابق هنا: «هذا البريد مسجَّل» تكشف حساباتنا
        [$signup, $code] = $this->svc->start($data['email'], $r->ip());
        $this->deliver($signup->email, $code, 'البريد', $signup->reference);

        return redirect("/register/brand/verify/{$signup->reference}");
    }

    // ===== 2) رمز البريد =====

    public function verifyEmailForm(string $reference): Response|RedirectResponse
    {
        $signup = $this->find($reference);

        return $signup->emailVerified()
            ? redirect("/register/brand/phone/{$reference}")
            : Inertia::render('Public/BrandSignup/VerifyEmail', $this->payload($signup));
    }

    public function verifyEmail(Request $r, string $reference): RedirectResponse
    {
        $signup = $this->find($reference);
        $data = $r->validate(['code' => 'required|string|size:6']);

        try {
            $this->svc->verifyEmail($signup, $data['code']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect("/register/brand/phone/{$reference}");
    }

    // ===== 3) الجوال =====

    public function phoneForm(string $reference): Response|RedirectResponse
    {
        $signup = $this->find($reference);

        if (! $signup->emailVerified()) {
            return redirect("/register/brand/verify/{$reference}");
        }

        return $signup->phoneVerified()
            ? redirect("/register/brand/details/{$reference}")
            : Inertia::render('Public/BrandSignup/Phone', $this->payload($signup));
    }

    public function startPhone(Request $r, string $reference): RedirectResponse
    {
        $signup = $this->find($reference);
        $data = $r->validate(['phone' => 'required|string|max:30'], [], ['phone' => 'رقم الجوال']);

        try {
            $code = $this->svc->startPhone($signup, $data['phone']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        $this->deliver($data['phone'], $code, 'الجوال', $reference);

        return back()->with('ok', 'أُرسل رمز التحقّق إلى جوالك.');
    }

    public function verifyPhone(Request $r, string $reference): RedirectResponse
    {
        $signup = $this->find($reference);
        $data = $r->validate(['code' => 'required|string|size:6']);

        try {
            $this->svc->verifyPhone($signup, $data['code']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect("/register/brand/details/{$reference}");
    }

    public function resend(Request $r, string $reference): RedirectResponse
    {
        $signup = $this->find($reference);
        $channel = $r->input('channel') === 'phone' ? 'phone' : 'email';

        try {
            $code = $this->svc->resend($signup, $channel);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $this->deliver(
            $channel === 'phone' ? (string) $signup->phone : $signup->email,
            $code,
            $channel === 'phone' ? 'الجوال' : 'البريد',
            $reference,
        );

        return back()->with('ok', 'أُرسل رمز جديد.');
    }

    // ===== 4) بيانات المؤسسة والعلامة، ثم المطابقة =====

    public function detailsForm(string $reference): Response|RedirectResponse
    {
        $signup = $this->find($reference);

        if (! $signup->fullyVerified()) {
            return redirect("/register/brand/verify/{$reference}");
        }

        return Inertia::render('Public/BrandSignup/Details', $this->payload($signup));
    }

    /**
     * يحفظ البيانات ويُجري المطابقة ثم يوجّه.
     *
     * التوجيه يقع في الخادم لا في الواجهة، والصفحتان لا تفرّقان في نبرتهما —
     * فلا يستدلّ المستخدم من الوجهة على أن علامته موجودة عندنا.
     */
    public function saveDetails(Request $r, string $reference): RedirectResponse
    {
        $signup = $this->find($reference);

        $data = $r->validate([
            'legal_name' => 'required|string|max:190',
            'commercial_registration' => 'nullable|string|max:60',
            'brand_name' => 'required|string|max:190',
            'sector' => 'nullable|string|max:120',
            'website' => 'nullable|string|max:190',
            'description' => 'nullable|string|max:1000',
            'social_accounts' => 'nullable|array|max:10',
            'social_accounts.*.platform' => 'required_with:social_accounts|string|max:40',
            'social_accounts.*.handle' => 'required_with:social_accounts|string|max:120',
        ], [], [
            'legal_name' => 'الاسم النظامي', 'brand_name' => 'اسم العلامة',
            'commercial_registration' => 'السجلّ التجاري', 'website' => 'الموقع الإلكتروني',
        ]);

        try {
            $this->svc->saveDetails(
                $signup,
                [
                    'legal_name' => $data['legal_name'],
                    'commercial_registration' => $data['commercial_registration'] ?? null,
                ],
                [
                    'name' => $data['brand_name'],
                    'sector' => $data['sector'] ?? null,
                    'website' => $data['website'] ?? null,
                    'description' => $data['description'] ?? null,
                    'social_accounts' => $data['social_accounts'] ?? [],
                ],
            );

            $signup = $this->svc->runMatch($signup->fresh(), $this->matcher);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['brand_name' => $e->getMessage()]);
        }

        // التطابق القويّ وحده يذهب إلى المطالبة؛ وما دونه يُكمل إنشاء مساحته
        return $signup->match_decision === BrandSignup::DECISION_STRONG
            ? redirect("/register/brand/verify-ownership/{$reference}")
            : redirect("/register/brand/owner/{$reference}");
    }

    // ===== 5أ) لا تطابق / محتمَل ⇐ إنشاء المساحة =====

    public function ownerForm(string $reference): Response|RedirectResponse
    {
        $signup = $this->find($reference);

        if ($signup->status !== 'matching' || ! $signup->brand_data) {
            return redirect("/register/brand/details/{$reference}");
        }

        if ($signup->match_decision === BrandSignup::DECISION_STRONG) {
            return redirect("/register/brand/verify-ownership/{$reference}");
        }

        return Inertia::render('Public/BrandSignup/Owner', $this->payload($signup) + [
            'brandName' => $signup->brand_data['name'] ?? '',
        ]);
    }

    public function complete(Request $r, string $reference, BrandProvisioningService $provisioner): RedirectResponse
    {
        $signup = $this->find($reference);

        $data = $r->validate([
            'owner_name' => 'required|string|max:120',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['owner_name' => 'اسمك', 'password' => 'كلمة المرور']);

        try {
            $result = $provisioner->provision($signup, [
                'name' => $data['owner_name'],
                'email' => $signup->email,
                'password' => $data['password'],
            ]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['owner_name' => $e->getMessage()]);
        }

        Auth::login($result['user']);
        $r->session()->regenerate();

        return redirect('/brand')->with('ok', 'أُنشئت مساحة علامتك وبدأت تجربتك.');
    }

    // ===== 5ب) تطابق قويّ ⇐ إثبات ملكية =====

    /**
     * صفحة إثبات الملكية.
     *
     * **لا تذكر العلامة المطابَقة ولا تؤكّد وجودها.** نصّها: «نحتاج إثبات
     * ملكيّتك لهذا الاسم قبل فتح المساحة» — وهي جملة صحيحة سواء وُجد سجلّ أو
     * لم يوجد، فلا يستفيد منها من يجرّب الأسماء.
     */
    public function verifyOwnershipForm(string $reference): Response|RedirectResponse
    {
        $signup = $this->find($reference);

        if ($signup->match_decision !== BrandSignup::DECISION_STRONG) {
            return redirect("/register/brand/owner/{$reference}");
        }

        $claim = BrandClaimRequest::where('signup_id', $signup->id)
            ->whereIn('status', BrandClaimRequest::LIVE)->first();

        return Inertia::render('Public/BrandSignup/VerifyOwnership', $this->payload($signup) + [
            'brandName' => $signup->brand_data['name'] ?? '',
            'claim' => $claim ? [
                'reference' => $claim->reference,
                'status' => $claim->status,
                'statusLabel' => $this->claimLabel($claim->status),
                'infoRequested' => $claim->info_requested,
                'documents' => $claim->documents()->count(),
            ] : null,
        ]);
    }

    public function submitClaim(Request $r, string $reference, BrandClaimService $claims): RedirectResponse
    {
        $signup = $this->find($reference);

        if ($signup->match_decision !== BrandSignup::DECISION_STRONG || ! $signup->matched_brand_id) {
            return redirect("/register/brand/owner/{$reference}");
        }

        $data = $r->validate([
            'statement' => 'required|string|max:2000',
            'role' => 'required|string|max:120',
        ], [], ['statement' => 'بيان الملكية', 'role' => 'صفتك']);

        // القراءة بتجاوز مقصود: العلامة في مستأجر آخر، ولا شيء منها يُعاد للطالب
        $brand = TenantContext::withBypass(
            fn () => Brand::withoutGlobalScopes()->find($signup->matched_brand_id),
        );

        if (! $brand) {
            return redirect("/register/brand/owner/{$reference}");
        }

        try {
            $claims->open($brand, $signup->email, $signup, [
                'statement' => $data['statement'], 'role' => $data['role'],
            ]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['statement' => $e->getMessage()]);
        }

        return back()->with('ok', 'استلمنا طلبك وسنراجعه.');
    }

    public function uploadDocument(Request $r, string $reference, BrandClaimService $claims): RedirectResponse
    {
        $signup = $this->find($reference);

        $r->validate([
            'document' => 'required|file|max:10240|mimes:pdf,png,jpg,jpeg',
            'type' => 'required|string|in:commercial_registration,authorization_letter,trademark,other',
        ], [], ['document' => 'المستند']);

        $claim = BrandClaimRequest::where('signup_id', $signup->id)
            ->whereIn('status', BrandClaimRequest::LIVE)->first();

        if (! $claim) {
            return back()->withErrors(['claim' => 'لا يوجد طلب مفتوح.']);
        }

        try {
            $claims->attachDocument($claim, $r->file('document'), $r->input('type'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        return back()->with('ok', 'أُضيف المستند.');
    }

    // ===== داخلي =====

    private function find(string $reference): BrandSignup
    {
        return BrandSignup::where('reference', $reference)->firstOrFail();
    }

    /** @return array<string,mixed> */
    private function payload(BrandSignup $signup): array
    {
        // ما يُرسَل للواجهة: حالة الرحلة فقط. لا `match_decision` ولا
        // `matched_brand_id` ولا `match_signals` — كلّها تكشف سجلّاتنا.
        return [
            'reference' => $signup->reference,
            'email' => $signup->email,
            'phone' => $signup->phone,
            'emailVerified' => $signup->emailVerified(),
            'phoneVerified' => $signup->phoneVerified(),
            'status' => $signup->status,
        ];
    }

    private function claimLabel(string $status): string
    {
        return match ($status) {
            BrandClaimRequest::PENDING => 'بانتظار المراجعة',
            BrandClaimRequest::UNDER_REVIEW => 'قيد المراجعة',
            BrandClaimRequest::MORE_INFO => 'بانتظار معلومات منك',
            BrandClaimRequest::APPROVED => 'مُعتمد',
            BrandClaimRequest::REJECTED => 'غير مُعتمد',
            BrandClaimRequest::EXPIRED => 'منتهٍ',
            BrandClaimRequest::CANCELLED => 'مُلغى',
            default => $status,
        };
    }

    /**
     * التسليم.
     *
     * لا مزوّد SMS مربوط بعد، فرمز الجوال يُكتب في السجلّ محليًّا ولا يُدَّعى
     * إرساله. وادّعاء تسليم لم يقع أسوأ من الاعتراف بغيابه.
     */
    private function deliver(string $to, string $code, string $channel, string $reference): void
    {
        if ($channel === 'البريد') {
            Mail::raw(
                "رمز تأكيد بريدك في إنفلونسر هَب: {$code}\nينتهي خلال 15 دقيقة.",
                fn ($m) => $m->to($to)->subject('رمز تأكيد البريد — إنفلونسر هَب'),
            );
        }

        if (! app()->environment('production')) {
            Log::info("[brand-signup] رمز {$channel} للمرجع {$reference}: {$code}");
        }
    }
}
