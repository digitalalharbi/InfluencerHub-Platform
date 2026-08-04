<?php
namespace App\Http\Controllers\Partner;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\ExternalAgencyMember;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PartnerAuthController extends Controller
{
    public function show()
    {
        return view('partner.login');
    }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (! Auth::validate($cred)) {
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة.']);
        }

        $user = User::where('email', $cred['email'])->first();
        $hasActive = $user && TenantContext::withBypass(fn () => ExternalAgencyMember::where('user_id', $user->id)->where('status', 'active')->exists());

        if (! $hasActive) {
            throw ValidationException::withMessages(['email' => 'لا توجد عضوية شريك فعّالة لهذا الحساب. اختر البوابة المناسبة للحساب.']);
        }

        Auth::attempt($cred, $r->boolean('remember'));
        $r->session()->regenerate();
        AuditLogger::log('partner.login', Auth::user(), [], null, Auth::id());

        return redirect()->intended('/partner/dashboard');
    }

    public function logout(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/partner/login');
    }

    public function switch(Request $r)
    {
        $data = $r->validate(['agency_id' => 'required|integer']);
        $ok = TenantContext::withBypass(fn () => ExternalAgencyMember::where('user_id', $r->user()->id)->where('external_agency_id', $data['agency_id'])->where('status', 'active')->exists());
        abort_unless($ok, 403);
        $r->session()->put('active_agency_id', $data['agency_id']);

        return redirect('/partner/dashboard')->with('ok', 'تم تبديل الوكالة.');
    }
}