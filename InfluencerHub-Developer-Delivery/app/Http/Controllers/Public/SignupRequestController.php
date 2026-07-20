<?php

namespace App\Http\Controllers\Public;

use App\Domain\Onboarding\Models\SignupRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تسجيل العميل والوكالة — طلب يُراجَع، لا تفعيل فوري.
 *
 * السبب مكتوب في هجرة signup_requests: التفعيل الفوري لمساحة وكالة يستلزم
 * باقة وتحصيلًا ماليًّا، والمزوّد غير مربوط. نقولها للمستخدم صراحةً في الصفحة
 * بدل أن نُظهر «أنشئ مساحتك الآن» ثم لا شيء يحدث.
 */
class SignupRequestController extends Controller
{
    /** الحقول المطلوبة لكل نوع — تُعرض في النموذج وتُتحقّق في الخادم. */
    private const RULES = [
        'contact_name' => 'required|string|max:120',
        'email' => 'required|email|max:190',
        'phone' => 'nullable|string|max:40',
        'company_name' => 'required|string|max:190',
        'website' => 'nullable|string|max:190',
        'country_code' => 'nullable|string|size:2',
        'team_size' => 'nullable|string|max:30',
        'monthly_campaigns' => 'nullable|string|max:30',
        'notes' => 'nullable|string|max:2000',
    ];

    public function form(Request $r, string $type): Response
    {
        abort_unless(isset(SignupRequest::TYPES[$type]), 404);

        return Inertia::render('Public/SignupRequest', [
            'accountType' => $type,
            'typeLabel' => SignupRequest::TYPES[$type],
        ]);
    }

    public function store(Request $r, string $type): RedirectResponse
    {
        abort_unless(isset(SignupRequest::TYPES[$type]), 404);
        $data = $r->validate(self::RULES);

        $signup = SignupRequest::create([
            ...$data,
            'account_type' => $type,
            'status' => 'submitted',
            'ip_address' => $r->ip(),
        ]);

        return redirect("/register/{$type}/submitted/{$signup->reference}");
    }

    public function submitted(Request $r, string $type, string $reference): Response
    {
        $signup = SignupRequest::where('reference', $reference)->where('account_type', $type)->firstOrFail();

        return Inertia::render('Public/SignupSubmitted', [
            'reference' => $signup->reference,
            'typeLabel' => $signup->typeLabel(),
            'email' => $signup->email,
        ]);
    }
}
