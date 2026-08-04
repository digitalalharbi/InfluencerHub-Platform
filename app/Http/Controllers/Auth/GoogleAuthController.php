<?php
namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\ExternalAgencyMember;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    private const PORTALS = ['agency', 'client', 'creator', 'partner'];

    public function redirect(Request $request): RedirectResponse
    {
        $portal = $this->portal($request->query('portal'));
        $request->session()->put('google_login_portal', $portal);

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $portal = $this->portal($request->session()->pull('google_login_portal', 'agency'));

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect($this->loginPath($portal))
                ->withErrors(['email' => 'تعذر تسجيل الدخول بواسطة Google. حاول مرة أخرى.']);
        }

        $email = strtolower((string) $googleUser->getEmail());
        if ($email === '') {
            return redirect($this->loginPath($portal))
                ->withErrors(['email' => 'لم ترسل Google بريدًا إلكترونيًا صالحًا لهذا الحساب.']);
        }

        $user = User::where('email', $email)
            ->orWhere('google_id', $googleUser->getId())
            ->first();

        if (! $user) {
            return redirect($this->loginPath($portal))
                ->withErrors(['email' => 'لا يوجد حساب في المنصة بهذا البريد الإلكتروني. سجّل أولًا أو استخدم بريد حسابك المسجل.']);
        }

        if (! $this->canUsePortal($user, $portal)) {
            return redirect($this->loginPath($portal))
                ->withErrors(['email' => 'هذا الحساب لا يملك صلاحية الدخول إلى هذه البوابة.']);
        }

        if (! $user->is_active) {
            return redirect($this->loginPath($portal))
                ->withErrors(['email' => 'هذا الحساب غير مفعّل حاليًا.']);
        }

        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'google_avatar' => $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        Auth::login($user, true);
        $request->session()->regenerate();
        AuditLogger::log("auth.google.{$portal}.login", $user, [], null, $user->id);

        return redirect()->intended($this->homePath($portal));
    }

    private function portal(mixed $portal): string
    {
        return in_array($portal, self::PORTALS, true) ? $portal : 'agency';
    }

    private function canUsePortal(User $user, string $portal): bool
    {
        return match ($portal) {
            'client' => TenantContext::withBypass(fn () => ClientMember::where('user_id', $user->id)->where('status', 'active')->exists()),
            'creator' => TenantContext::withBypass(fn () => Creator::where('user_id', $user->id)->exists()),
            'partner' => TenantContext::withBypass(fn () => ExternalAgencyMember::where('user_id', $user->id)->where('status', 'active')->exists()),
            default => $this->canUseAgencyPortal($user),
        };
    }

    private function canUseAgencyPortal(User $user): bool
    {
        $agencyRoles = [
            Role::SystemAdmin->value,
            Role::SuperAdmin->value,
            Role::AgencyAdmin->value,
            Role::AgencyEmployee->value,
            Role::OperationsManager->value,
            Role::CampaignManager->value,
            Role::CreatorManager->value,
            Role::Finance->value,
            Role::ContentReviewer->value,
        ];

        return $user->is_system_admin || TenantContext::withBypass(fn () =>
            $user->memberships()->where('status', 'active')->whereIn('role', $agencyRoles)->exists()
        );
    }

    private function loginPath(string $portal): string
    {
        return match ($portal) {
            'client' => '/client/login',
            'creator' => '/creator/login',
            'partner' => '/partner/login',
            default => '/login',
        };
    }

    private function homePath(string $portal): string
    {
        return match ($portal) {
            'client' => '/client/dashboard',
            'creator' => '/creator/dashboard',
            'partner' => '/partner/dashboard',
            default => '/app',
        };
    }
}
