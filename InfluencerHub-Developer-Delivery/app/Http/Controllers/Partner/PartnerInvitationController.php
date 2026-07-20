<?php
namespace App\Http\Controllers\Partner;
use App\Domain\Partners\Services\AcceptPartnerInvitation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
/** قبول دعوة الشريك (عام، مُحصّن، مُقيّد المعدّل). */
class PartnerInvitationController extends Controller {
    public function show(string $token, AcceptPartnerInvitation $svc) {
        try { $inv = $svc->resolve($token); }
        catch (\RuntimeException $e) { return view('partner.invite-invalid', ['message' => $e->getMessage()]); }
        return view('partner.accept-invite', ['token' => $token, 'email' => $inv->email]);
    }
    public function accept(string $token, Request $r, AcceptPartnerInvitation $svc) {
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['name' => 'الاسم', 'password' => 'كلمة المرور']);
        try { [$user, $member] = $svc->accept($token, $data['name'], $data['password']); }
        catch (\RuntimeException $e) { return back()->withErrors(['invite' => $e->getMessage()]); }
        Auth::login($user);
        $r->session()->regenerate();
        return redirect('/partner/dashboard')->with('ok', 'تم قبول الدعوة وإنشاء حسابك.');
    }
}
