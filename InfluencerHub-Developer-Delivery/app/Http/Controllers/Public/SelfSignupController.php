<?php

namespace App\Http\Controllers\Public;

use App\Domain\Onboarding\Models\SelfSignup;
use App\Domain\Onboarding\Services\SelfSignupService;
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
 * المسار الذاتي لإنشاء مساحة وكالة.
 *
 * الوكالة وحدها تُنشئ مستأجرًا ذاتيًّا: العميل في هذا المنتَج سجلّ داخل مستأجر
 * وكالة، فلا معنى لمستأجر عميل بلا وكالة. تسجيل العميل يبقى مسار مطابقة
 * (SignupRequestController) وهذا ليس تراجعًا بل ما يقتضيه نموذج البيانات.
 */
class SelfSignupController extends Controller
{
    public function __construct(private SelfSignupService $svc) {}

    public function startForm(): Response
    {
        return Inertia::render('Public/SelfSignup/Start', [
            'steps' => SelfSignup::STEPS,
        ]);
    }

    public function start(Request $r): RedirectResponse
    {
        $data = $r->validate(
            ['email' => 'required|email|max:190'],
            [],
            ['email' => 'البريد الإلكتروني'],
        );

        [$signup, $code] = $this->svc->start($data['email'], 'agency', $r->ip());
        $this->deliverCode($signup, $code);

        return redirect("/register/agency/verify/{$signup->reference}");
    }

    public function verifyForm(string $reference): Response
    {
        $signup = SelfSignup::where('reference', $reference)->firstOrFail();

        return Inertia::render('Public/SelfSignup/Verify', $this->payload($signup));
    }

    public function verify(Request $r, string $reference): RedirectResponse
    {
        $signup = SelfSignup::where('reference', $reference)->firstOrFail();
        $data = $r->validate(['code' => 'required|string|size:6']);

        try {
            $this->svc->verify($signup, $data['code']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect("/register/agency/setup/{$signup->reference}");
    }

    public function resend(string $reference): RedirectResponse
    {
        $signup = SelfSignup::where('reference', $reference)->firstOrFail();
        if ($signup->isVerified()) {
            return back();
        }

        [$fresh, $code] = $this->svc->start($signup->email, 'agency');
        $this->deliverCode($fresh, $code);

        return redirect("/register/agency/verify/{$fresh->reference}")->with('ok', 'أُرسل رمز جديد.');
    }

    public function setupForm(string $reference): Response|RedirectResponse
    {
        $signup = SelfSignup::where('reference', $reference)->firstOrFail();
        if (! $signup->isVerified()) {
            return redirect("/register/agency/verify/{$signup->reference}");
        }

        return Inertia::render('Public/SelfSignup/Setup', $this->payload($signup));
    }

    /** آخر خطوة: تُنشأ المساحة ويُسجَّل الدخول فورًا — لا نطلب كلمة المرور مرّتين. */
    public function complete(Request $r, string $reference): RedirectResponse
    {
        $signup = SelfSignup::where('reference', $reference)->firstOrFail();
        $data = $r->validate([
            'owner_name' => 'required|string|max:120',
            'organization_name' => 'required|string|max:190',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], [
            'owner_name' => 'الاسم', 'organization_name' => 'اسم الوكالة', 'password' => 'كلمة المرور',
        ]);

        try {
            $user = $this->svc->provision($signup, $data);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['setup' => $e->getMessage()]);
        }

        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/app')->with('ok', 'أُنشئت مساحتك وبدأت تجربتك المجانية.');
    }

    /**
     * التسليم عبر بريد التطبيق. في التطوير سائق البريد `log`، فيُكتب الرمز في
     * السجلّ ليمكن إكمال المسار محليًّا — ولا يظهر في أي واجهة.
     */
    private function deliverCode(SelfSignup $signup, string $code): void
    {
        Mail::raw(
            "رمز تأكيد بريدك في إنفلونسر هَب: {$code}\nينتهي خلال 15 دقيقة.",
            fn ($m) => $m->to($signup->email)->subject('رمز تأكيد البريد — إنفلونسر هَب'),
        );

        if (! app()->environment('production')) {
            Log::info("[self-signup] رمز التحقّق للمرجع {$signup->reference}: {$code}");
        }
    }

    /** @return array<string,mixed> */
    private function payload(SelfSignup $signup): array
    {
        return [
            'reference' => $signup->reference,
            'email' => $signup->email,
            'status' => $signup->status,
            'steps' => SelfSignup::STEPS,
            'completedSteps' => $signup->completed_steps ?? [],
        ];
    }
}
