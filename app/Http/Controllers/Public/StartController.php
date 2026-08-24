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
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class StartController extends Controller
{
    public const CARRIED = ['type', 'email', 'referral', 'plan', 'utm_source', 'utm_medium', 'utm_campaign'];

    public function __construct(
        private BrandSignupService $brandSignups,
        private SelfSignupService $selfSignups,
    ) {}

    public function index(Request $r): Response|RedirectResponse
    {
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

    private function beginBrand(string $email, ?string $ip, array $carry): RedirectResponse
    {
        [$signup, $code] = $this->brandSignups->start($email, $ip);
        $this->deliver($email, $code, $signup->reference, AccountTypes::BRAND);

        return redirect($this->withQuery("/register/brand/verify/{$signup->reference}", $carry));
    }

    private function beginAgency(string $email, ?string $ip, array $carry): RedirectResponse
    {
        [$signup, $code] = $this->selfSignups->start($email, AccountTypes::AGENCY, $ip);
        $this->deliver($email, $code, $signup->reference, AccountTypes::AGENCY);

        return redirect($this->withQuery("/register/agency/verify/{$signup->reference}", $carry));
    }

    /** @return array<string,string> */
    private function carried(Request $r, array $except = []): array
    {
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

    private function deliver(string $email, string $code, string $reference, string $type): void
    {
        $route = $type === AccountTypes::BRAND
            ? 'register.brand.verify-link'
            : 'register.agency.verify-link';

        $verifyUrl = URL::temporarySignedRoute(
            $route,
            now()->addMinutes(15),
            ['reference' => $reference, 'code' => $code],
        );

        Mail::send(
            'mail.verification-code',
            ['code' => $code, 'verifyUrl' => $verifyUrl],
            fn ($m) => $m->to($email)->subject('رمز تأكيد البريد — InfluencerHub'),
        );

        if (! app()->environment('production')) {
            Log::info("[start] رمز التحقق لـ{$email}: {$code}");
        }
    }
}
