<?php

namespace App\Http\Controllers\Creator;

use App\Domain\Creators\Models\{Creator, CreatorInvitation};
use App\Domain\Creators\Services\CreatorInvitationService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Inertia\Inertia;

/**
 * قبول دعوة صانع المحتوى — تدفّق عامّ غير مصادَق.
 *
 * الرمز في الرابط هو الإذن الوحيد، فكل خطوة تُعيد التحقّق منه ولا تعتمد على
 * جلسة. والمحاولات محدودة بالمعدّل: الرمز الرقمي قصير، وبلا حدّ يُخمَّن.
 */
class InvitationAcceptController extends Controller
{
    public function __construct(private CreatorInvitationService $svc) {}

    public function show(Request $r, string $token)
    {
        $inv = $this->svc->findByToken($token);
        if (! $inv) {
            return Inertia::render('Public/InvitationInvalid', ['reason' => 'هذا الرابط غير معروف.']);
        }
        if ($reason = $inv->unusableReason()) {
            return Inertia::render('Public/InvitationInvalid', ['reason' => $reason]);
        }

        $creator = TenantContext::withBypass(fn () => Creator::find($inv->creator_id));

        return Inertia::render('Public/InvitationAccept', [
            'token' => $token,
            'creatorName' => $creator?->display_name,
            'email' => $inv->email,
            'phone' => $inv->phone,
            'emailVerified' => (bool) $inv->email_verified_at,
            'phoneVerified' => (bool) $inv->phone_verified_at,
            'needsPhone' => (bool) $inv->phone,
        ]);
    }

    public function verifyEmail(Request $r, string $token)
    {
        return $this->verify($r, $token, 'email');
    }

    public function verifyPhone(Request $r, string $token)
    {
        return $this->verify($r, $token, 'phone');
    }

    private function verify(Request $r, string $token, string $channel)
    {
        $code = $r->validate(['code' => 'required|string|max:8'])['code'];
        $inv = $this->svc->findByToken($token);
        abort_unless($inv, 404);

        // الرمز 6 خانات: بلا حدّ معدّل يُستنفَد المجال بالتخمين
        $key = "creator-invite:{$channel}:{$inv->id}";
        if (RateLimiter::tooManyAttempts($key, 6)) {
            return back()->withErrors(['code' => 'محاولات كثيرة — انتظر دقيقة ثم أعِد المحاولة.']);
        }

        try {
            $channel === 'email' ? $this->svc->verifyEmail($inv, $code) : $this->svc->verifyPhone($inv, $code);
        } catch (\RuntimeException $e) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['code' => $e->getMessage()]);
        }

        RateLimiter::clear($key);

        return back()->with('ok', 'تم التحقّق.');
    }

    public function accept(Request $r, string $token)
    {
        $data = $r->validate(['password' => 'required|string|min:8|confirmed']);
        $inv = $this->svc->findByToken($token);
        abort_unless($inv, 404);

        try {
            $user = $this->svc->accept($inv, $data['password']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }

        Auth::login($user);

        return redirect('/creator/dashboard')->with('ok', 'فُعِّلت بوابتك — أهلًا بك.');
    }
}
