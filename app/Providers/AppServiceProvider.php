<?php

namespace App\Providers;

use App\Domain\AdminPool\Assistant\OpenAiAssistant;
use App\Domain\AdminPool\Assistant\RuleBasedAssistant;
use App\Domain\AdminPool\Assistant\ShortlistAssistant;
use App\Domain\Automation\Actions\NotifyAction;
use App\Domain\Automation\Engine\ActionRegistry;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Policies\CampaignPolicy;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Collaborations\Policies\CollaborationPolicy;
use App\Domain\Communications\Channels\ChannelRegistry;
use App\Domain\Communications\Channels\EmailChannel;
use App\Domain\Communications\Channels\InAppChannel;
use App\Domain\Communications\Channels\SmsChannel;
use App\Domain\Communications\Channels\WhatsAppChannel;
use App\Domain\Communications\Listeners\AdvanceEmailDeliveryOnSent;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Policies\ContentPolicy;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Policies\ContractPolicy;
use App\Domain\Creators\Contracts\OtpMailer;
use App\Domain\Creators\Contracts\OtpSmsSender;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Models\CreatorApplication;
use App\Domain\Creators\Policies\CreatorApplicationPolicy;
use App\Domain\Creators\Policies\CreatorPolicy;
use App\Domain\Creators\Services\Otp\LogOtpMailer;
use App\Domain\Creators\Services\Otp\MailOtpMailer;
use App\Domain\Creators\Services\Otp\NullSmsSender;
use App\Domain\Creators\Services\Otp\TwilioVerifySmsSender;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\Client;
use App\Domain\CRM\Policies\BrandPolicy;
use App\Domain\CRM\Policies\ClientPolicy;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Policies\InvoicePolicy;
use App\Domain\Finance\Policies\PayoutPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Adapters\AdapterRegistry;
use App\Domain\Partners\Models\ExternalAgency;
use App\Domain\Partners\Policies\ExternalAgencyPolicy;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Requests\Policies\ServiceRequestPolicy;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // سجلّ محوّلات التكامل — فارغ افتراضًا؛ يُسجَّل مزوّد حقيقي حين تتوفّر بيانات
        // اعتماده (BLOCKED_EXTERNAL حتى ذلك). الإطار (تشغيلات/جدولة/معالجة) جاهز.
        $this->app->singleton(AdapterRegistry::class, fn () => new AdapterRegistry([]));

        // سجلّ قنوات التسليم — الترتيب مقصود (in_app أوّلًا). القنوات الخارجية
        // متاحة فقط حين تُفعَّل بيانات اعتمادها؛ افتراضها غير مهيّأ.
        $this->app->singleton(ChannelRegistry::class, fn ($app) => new ChannelRegistry([
            $app->make(InAppChannel::class),
            $app->make(EmailChannel::class),
            $app->make(WhatsAppChannel::class),
            $app->make(SmsChannel::class),
        ]));

        // سجلّ إجراءات الأتمتة — إشعار (وقابل للتوسّع بمهمة/تصعيد). لا إجراء مالي.
        $this->app->singleton(ActionRegistry::class, fn ($app) => new ActionRegistry([
            $app->make(NotifyAction::class),
        ]));

        // مساعد الترشيح: يختار السائق من الإعداد، ويرتدّ إلى القواعد إن لم يُربَط OpenAI
        $this->app->bind(ShortlistAssistant::class, function () {
            $rule = new RuleBasedAssistant;
            if (config('services.pool_assistant.driver') === 'openai') {
                return new OpenAiAssistant(
                    config('services.pool_assistant.openai_key'), $rule,
                    config('services.pool_assistant.openai_model'),
                    config('services.pool_assistant.openai_base_url'),
                    (int) config('services.pool_assistant.openai_timeout'),
                );
            }

            return $rule;
        });

        // قنوات OTP: بريد فعلي في الإنتاج، تسجيل محلي بلا SMTP؛ SMS بلا مزوّد → waiting_for_credentials
        $this->app->bind(OtpMailer::class, fn ($app) => $app->environment('production')
                ? new MailOtpMailer
                : new LogOtpMailer);
        $this->app->bind(OtpSmsSender::class, fn () => config('services.twilio.mobile_provider') === 'twilio'
            && config('services.twilio.sid')
            && config('services.twilio.auth_token')
            && (config('services.twilio.verify_sid') || config('services.twilio.whatsapp_from'))
                ? new TwilioVerifySmsSender
                : new NullSmsSender);
    }

    public function boot(): void
    {
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Creator::class, CreatorPolicy::class);
        Gate::policy(CreatorApplication::class, CreatorApplicationPolicy::class);
        Gate::policy(ExternalAgency::class, ExternalAgencyPolicy::class);
        Gate::policy(ServiceRequest::class, ServiceRequestPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Collaboration::class, CollaborationPolicy::class);
        Gate::policy(ContentItem::class, ContentPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(Payout::class, PayoutPolicy::class);

        // مدير النظام يتجاوز كل السياسات — لكن التجاوز يُسجَّل في SetTenantContext/AuditLogger.
        Gate::before(fn (User $user) => $user->is_system_admin ? true : null);

        // تقديم حالة تسليم البريد queued→sent من حدث النقل الفعليّ (لا ادّعاء «سُلِّم»).
        Event::listen(
            MessageSent::class,
            AdvanceEmailDeliveryOnSent::class,
        );

        // شارة "بيانات تجريبية" العامة: تظهر في كل التخطيطات عند تصفّح مستأجر العرض (slug=showcase).
        View::composer(
            ['layouts.app', 'client.layout', 'partner.layout', 'creator.layout'],
            function ($view) {
                $tid = TenantContext::tenantId();
                $isShowcase = $tid
                    && Tenant::withoutGlobalScopes()->whereKey($tid)->value('slug') === 'showcase';
                $view->with('ihShowcase', (bool) $isShowcase);
            }
        );

        // حدود مركّبة للبوابة العامة (لا تعتمد IP وحده — عدة مستخدمين قد يتشاركون الشبكة).
        // الرسائل موحّدة ولا تكشف وجود بريد/طلب.
        RateLimiter::for('join-start', fn ($r) => [
            Limit::perMinute(30)->by('ip:'.$r->ip()),
            Limit::perMinute(5)->by('email:'.sha1((string) $r->input('email'))),
        ]);
        RateLimiter::for('join-otp', fn ($r) => [
            Limit::perMinute(6)->by('ref:'.$r->route('reference')),
            Limit::perMinute(10)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('join-recover', fn ($r) => [
            Limit::perMinute(5)->by('email:'.sha1((string) $r->input('email'))),
            Limit::perMinute(5)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('join-op', fn ($r) => [
            Limit::perMinute(30)->by('ref:'.$r->route('reference')),
        ]);

        // تحديد معدّل تسجيل الدخول (كل البوّابات) — يمنع التخمين العنيف. مفتاح مركّب
        // (بريد مُجزّأ + IP) فلا يُقفل شبكة كاملة، ومفتاح IP فضفاض ضد التخمين الموزّع.
        // الرسالة على القفل عامّة (429) ولا تكشف وجود بريد (لا account enumeration).
        RateLimiter::for('login', function ($r) {
            // بيئة E2E فقط تعطّل الحدّ (مئات عمليات دخول بحسابات قليلة عبر كاش مشترك).
            if (config('app.disable_login_throttle')) {
                return Limit::none();
            }

            return [
                Limit::perMinute(20)->by('login:'.sha1((string) $r->input('email')).'|'.$r->ip()),
                Limit::perMinute(60)->by('login-ip:'.$r->ip()),
            ];
        });
    }
}
