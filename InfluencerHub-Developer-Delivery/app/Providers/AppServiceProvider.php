<?php

namespace App\Providers;

use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\CRM\Policies\{BrandPolicy, ClientPolicy};
use App\Domain\Creators\Models\{Creator, CreatorApplication};
use App\Domain\Creators\Policies\{CreatorPolicy, CreatorApplicationPolicy};
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // قنوات OTP: بريد فعلي في الإنتاج، تسجيل محلي بلا SMTP؛ SMS بلا مزوّد → waiting_for_credentials
        $this->app->bind(\App\Domain\Creators\Contracts\OtpMailer::class, fn ($app) =>
            $app->environment('production')
                ? new \App\Domain\Creators\Services\Otp\MailOtpMailer()
                : new \App\Domain\Creators\Services\Otp\LogOtpMailer());
        $this->app->bind(\App\Domain\Creators\Contracts\OtpSmsSender::class, \App\Domain\Creators\Services\Otp\NullSmsSender::class);
    }

    public function boot(): void
    {
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Creator::class, CreatorPolicy::class);
        Gate::policy(CreatorApplication::class, CreatorApplicationPolicy::class);
        Gate::policy(\App\Domain\Partners\Models\ExternalAgency::class, \App\Domain\Partners\Policies\ExternalAgencyPolicy::class);
        Gate::policy(\App\Domain\Requests\Models\ServiceRequest::class, \App\Domain\Requests\Policies\ServiceRequestPolicy::class);
        Gate::policy(\App\Domain\Finance\Models\Invoice::class, \App\Domain\Finance\Policies\InvoicePolicy::class);
        Gate::policy(\App\Domain\Campaigns\Models\Campaign::class, \App\Domain\Campaigns\Policies\CampaignPolicy::class);
        Gate::policy(\App\Domain\Collaborations\Models\Collaboration::class, \App\Domain\Collaborations\Policies\CollaborationPolicy::class);
        Gate::policy(\App\Domain\Content\Models\ContentItem::class, \App\Domain\Content\Policies\ContentPolicy::class);
        Gate::policy(\App\Domain\Contracts\Models\Contract::class, \App\Domain\Contracts\Policies\ContractPolicy::class);
        Gate::policy(\App\Domain\Finance\Models\Payout::class, \App\Domain\Finance\Policies\PayoutPolicy::class);

        // مدير النظام يتجاوز كل السياسات — لكن التجاوز يُسجَّل في SetTenantContext/AuditLogger.
        Gate::before(fn (User $user) => $user->is_system_admin ? true : null);

        // شارة "بيانات تجريبية" العامة: تظهر في كل التخطيطات عند تصفّح مستأجر العرض (slug=showcase).
        \Illuminate\Support\Facades\View::composer(
            ['layouts.app', 'client.layout', 'partner.layout', 'creator.layout'],
            function ($view) {
                $tid = \App\Domain\Tenancy\Support\TenantContext::tenantId();
                $isShowcase = $tid
                    && \App\Domain\Tenancy\Models\Tenant::withoutGlobalScopes()->whereKey($tid)->value('slug') === 'showcase';
                $view->with('ihShowcase', (bool) $isShowcase);
            }
        );

        // حدود مركّبة للبوابة العامة (لا تعتمد IP وحده — عدة مستخدمين قد يتشاركون الشبكة).
        // الرسائل موحّدة ولا تكشف وجود بريد/طلب.
        \Illuminate\Support\Facades\RateLimiter::for('join-start', fn ($r) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by('ip:' . $r->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('email:' . sha1((string) $r->input('email'))),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('join-otp', fn ($r) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(6)->by('ref:' . $r->route('reference')),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('ip:' . $r->ip()),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('join-recover', fn ($r) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('email:' . sha1((string) $r->input('email'))),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('ip:' . $r->ip()),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('join-op', fn ($r) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by('ref:' . $r->route('reference')),
        ]);
    }
}
