<?php

namespace App\Http\Controllers\Public;

use App\Domain\Billing\Exceptions\EntitlementLimitExceeded;
use App\Domain\Creators\Models\CreatorApplication;
use App\Domain\Creators\Models\CreatorApplicationPlatform;
use App\Domain\Creators\Models\CreatorApplicationPortfolio;
use App\Domain\Creators\Models\CreatorCategory;
use App\Domain\Creators\Services\ApplicationDocumentService;
use App\Domain\Creators\Services\CreatorApplicationService;
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Creators\Services\CreatorEntitlementService;
use App\Domain\Creators\Support\FinancialCrypto;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RuntimeException;

/** بوابة انضمام عامة (بلا تسجيل دخول). المرجع عشوائي غير قابل للتخمين. */
class JoinController extends Controller
{
    public function __construct(private CreatorApplicationService $svc) {}

    public function index()
    {
        return view('join.index');
    }

    public function creatorForm(Request $r)
    {
        // حلّ صريح: نمرّر slug المؤسسة للنموذج (لا مؤسسة افتراضية مخفية)
        return view('join.creator', [
            'categories' => $this->categories(),
            'slug' => $r->query('a'),
            'capabilityOptions' => CreatorCapabilityService::options(),
        ]);
    }

    public function storeCreator(Request $r)
    {
        // القدرات تُجمَع: المتقدّم يصف نفسه بما يجيده كاملًا لا بخانة واحدة.
        // التحقّق من جهة الخادم لأن الواجهة تستطيع إرسال صفر قدرات (خانات اختيار).
        $data = $r->validate([
            ...CreatorCapabilityService::rules(),
            'full_name' => 'required|string|max:160',
            'email' => 'required|email|max:160',
            'phone' => 'required|string|max:30',
            'country_code' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:120',
            'terms' => 'accepted',
            'privacy' => 'accepted',
        ], CreatorCapabilityService::messages());
        $data['capabilities'] = CreatorCapabilityService::normalize($data['capabilities']);
        // كتابة مزدوجة انتقالية: `account_type` ما يزال يُقرأ في شاشات المراجعة
        // وفي اشتقاق دور العضوية عند القبول، فيُحدَّث مشتقًّا من القدرات لا مهملًا.
        $data['account_type'] = CreatorCapabilityService::legacyType($data['capabilities']);
        $slug = $r->route('workspace') ?? $r->query('a');
        $ctx = $this->svc->resolveTenantContext($slug);
        abort_if(! $ctx, 404, 'الوكالة غير متاحة'); // fail-closed: لا "أول مستأجر"
        [$tenant, $orgResolved, $source] = $ctx;
        // External Portal مفعّلة؟
        $entChk = app(CreatorEntitlementService::class);
        abort_if(! $entChk->portalEnabled($orgResolved) && ! $entChk->orgForTenant($tenant->id), 404);

        // ugc_creator.enabled: البوابة على قدرة UGC نفسها لا على النص المشتقّ منها
        $ent = app(CreatorEntitlementService::class);
        if ($org = $ent->orgForTenant($tenant->id)) {
            try {
                $ent->assertCapabilitiesAllowed($org, $data['capabilities']);
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['capabilities' => $e->getMessage()]);
            }
        }

        // منع الطلبات المكرّرة النشِطة لنفس البريد
        $dupe = TenantContext::withBypass(fn () => CreatorApplication::where('tenant_id', $tenant->id)->where('email', $data['email'])
            ->whereNotIn('status', ['rejected', 'withdrawn', 'archived'])->exists());
        if ($dupe) {
            return back()->withInput()->withErrors(['email' => 'يوجد طلب سابق بهذا البريد. تابع عبر رابط المتابعة أو تواصل مع الوكالة.']);
        }

        $app = $this->svc->startDraft($tenant, $data + ['country_code' => $data['country_code'] ?? 'SA']);
        TenantContext::withTenant($app->tenant_id, function () use ($app, $slug, $source) {
            $app->update(['terms_accepted_at' => now(), 'privacy_accepted_at' => now(), 'workspace_slug' => $slug, 'tenant_resolution_source' => $source]);
        });
        $this->svc->issueAccessToken($app);           // رمز منفصل عن المرجع (للاستعادة عبر البريد)
        $r->session()->put("app_access_{$app->id}", true); // جلسة المتقدّم

        return redirect("/join/creator/{$app->reference}/status")->with('ok', 'تم إنشاء طلبك كمسودة. احفظ هذا الرابط أو استعده عبر بريدك.');
    }

    public function status(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        TenantContext::withBypass(function () use ($app) {
            $app->load('documents', 'platforms', 'services', 'portfolios');
        });

        return view('join.status', ['app' => $app, 'categories' => $this->categories()]);
    }

    public function continue(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        $data = $r->validate([
            'professional_name' => 'nullable|string|max:160',
            'whatsapp' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:120',
            'gender' => 'nullable|in:male,female,other',
            'bio' => 'nullable|string|max:2000',
            'categories' => 'nullable|array', 'categories.*' => 'string|max:60',
            'languages' => 'nullable|array', 'languages.*' => 'string|max:30',
        ]);
        try {
            $this->svc->updateDraft($app, array_merge($data, ['current_step' => max($app->current_step, 2)]));
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }

        return back()->with('ok', 'تم حفظ التقدّم.');
    }

    public function verifyEmail(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        if ($app->email_verified_at) {
            return back()->with('ok', 'البريد مُتحقَّق منه مسبقًا.');
        }

        if ($r->filled('code')) {
            try {
                $ok = $this->svc->verifyOtp($app, 'email', $r->input('code'));
            } catch (RuntimeException $e) {
                return back()->withErrors(['code' => $e->getMessage()]);
            }

            return $ok ? back()->with('ok', 'تم التحقق من البريد.') : back()->withErrors(['code' => 'رمز غير صحيح.']);
        }
        // إصدار رمز جديد (يُرسَل عبر Queue في الإنتاج؛ محليًا نعرضه للمعاينة فقط)
        $code = $this->svc->issueOtp($app, 'email');
        $flash = app()->environment('production') ? [] : ['dev_otp' => $code];

        return back()->with('ok', 'أُرسل رمز التحقق إلى بريدك.')->with($flash);
    }

    public function verifyPhone(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        if ($app->phone_verified_at) {
            return back()->with('ok', 'الجوال مُتحقَّق منه مسبقًا.');
        }
        if ($r->filled('code')) {
            try {
                $ok = $this->svc->verifyOtp($app, 'phone', $r->input('code'));
            } catch (RuntimeException $e) {
                return back()->withErrors(['phone_code' => $e->getMessage()]);
            }

            return $ok ? back()->with('ok', 'تم التحقق من الجوال.') : back()->withErrors(['phone_code' => 'رمز غير صحيح.']);
        }
        $code = $this->svc->issueOtp($app, 'phone');
        $flash = app()->environment('production') ? [] : ['dev_otp_phone' => $code];

        return back()->with('ok', 'أُرسل رمز التحقق إلى جوالك (جاهزية).')->with($flash);
    }

    public function addPlatform(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        if (! $app->isEditableByApplicant()) {
            return back()->withErrors(['platform' => 'الطلب غير قابل للتعديل الآن.']);
        }
        $data = $r->validate(['platform' => 'required|in:instagram,tiktok,youtube,snapchat,x,linkedin,other',
            'username' => 'required|string|max:120', 'profile_url' => 'nullable|url|max:255',
            'followers_count' => 'nullable|integer|min:0', 'average_views' => 'nullable|integer|min:0', 'engagement_rate' => 'nullable|numeric|min:0|max:100']);
        // social_integrations.max
        $ent = app(CreatorEntitlementService::class);
        if ($org = $ent->orgForTenant($app->tenant_id)) {
            try {
                $ent->assertSocialWithinLimit($org, $app);
            } catch (RuntimeException $e) {
                return back()->withErrors(['platform' => $e->getMessage()]);
            }
        }
        TenantContext::withTenant($app->tenant_id, function () use ($app, $data) {
            CreatorApplicationPlatform::create($data + ['tenant_id' => $app->tenant_id, 'application_id' => $app->id, 'source' => 'applicant', 'status' => 'manual_unverified']);
        });

        return back()->with('ok', 'أُضيف الحساب الاجتماعي.');
    }

    public function addService(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        if (! $app->isEditableByApplicant()) {
            return back()->withErrors(['service' => 'الطلب غير قابل للتعديل الآن.']);
        }
        $data = $r->validate(['service_type' => 'required|string|max:30', 'price' => 'nullable|numeric|min:0',
            'delivery_days' => 'nullable|integer|min:0', 'revision_rounds' => 'nullable|integer|min:0',
            'usage_rights_days' => 'nullable|integer|min:0', 'description' => 'nullable|string|max:500']);
        TenantContext::withTenant($app->tenant_id, function () use ($app, $data) {
            \App\Domain\Creators\Models\CreatorApplicationService::create(['tenant_id' => $app->tenant_id, 'application_id' => $app->id,
                'service_type' => $data['service_type'], 'price_minor' => isset($data['price']) ? (int) round($data['price'] * 100) : null,
                'currency' => 'SAR', 'delivery_days' => $data['delivery_days'] ?? null, 'revision_rounds' => $data['revision_rounds'] ?? null,
                'usage_rights_days' => $data['usage_rights_days'] ?? null, 'description' => $data['description'] ?? null, 'is_available' => true]);
        });

        return back()->with('ok', 'أُضيفت الخدمة.');
    }

    public function addPortfolio(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        if (! $app->isEditableByApplicant()) {
            return back()->withErrors(['portfolio' => 'الطلب غير قابل للتعديل الآن.']);
        }
        $data = $r->validate(['type' => 'required|in:image,video,link', 'url' => 'nullable|url|max:255',
            'category' => 'nullable|string|max:60', 'previous_brand' => 'nullable|string|max:160', 'description' => 'nullable|string|max:500']);
        TenantContext::withTenant($app->tenant_id, function () use ($app, $data) {
            CreatorApplicationPortfolio::create($data + ['tenant_id' => $app->tenant_id, 'application_id' => $app->id, 'status' => 'submitted']);
        });

        return back()->with('ok', 'أُضيف نموذج العمل.');
    }

    public function saveMowthooq(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        $data = $r->validate(['mowthooq_license_number' => 'nullable|string|max:120', 'mowthooq_issued_at' => 'nullable|date', 'mowthooq_expires_at' => 'nullable|date|after:mowthooq_issued_at']);
        try {
            // المتقدّم لا يختار verified — الحالة الابتدائية pending عند الإدخال
            $this->svc->updateDraft($app, $data + ['mowthooq_status' => $data['mowthooq_license_number'] ?? false ? 'pending' : 'not_provided']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['mowthooq' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُفظت بيانات موثوق (بانتظار المراجعة).');
    }

    public function saveFinancial(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        $data = $r->validate(['beneficiary_name' => 'nullable|string|max:160', 'bank_name' => 'nullable|string|max:120',
            'iban' => 'nullable|string|max:40', 'tax_number' => 'nullable|string|max:40']);
        $update = ['beneficiary_name' => $data['beneficiary_name'] ?? $app->beneficiary_name, 'bank_name' => $data['bank_name'] ?? $app->bank_name, 'tax_number' => $data['tax_number'] ?? $app->tax_number];
        if (! empty($data['iban'])) {
            $update = array_merge($update, FinancialCrypto::encryptIban($data['iban']));
            $update['financial_verification_status'] = 'pending'; // لا يعتمد نفسه
        }
        try {
            $this->svc->updateDraft($app, $update);
        } catch (RuntimeException $e) {
            return back()->withErrors(['financial' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُفظت البيانات المالية (الآيبان مشفّر).');
    }

    public function uploadDocument(Request $r, string $reference, ApplicationDocumentService $docs)
    {
        $app = $this->authorizedApp($reference, $r);
        if (! $app->isEditableByApplicant()) {
            return back()->withErrors(['file' => 'لا يمكن رفع ملفات في حالة الطلب الحالية.']);
        }
        $data = $r->validate(['category' => 'required|string', 'file' => 'required|file']);
        try {
            // creator_storage.gb: يرفض الرفع قبل تجاوز التخزين
            $ent = app(CreatorEntitlementService::class);
            if ($org = $ent->orgForTenant($app->tenant_id)) {
                $ent->assertStorageAvailable($org, (int) $r->file('file')->getSize());
            }
            $docs->upload($app, $data['category'], $r->file('file'), null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('ok', 'تم رفع الملف بنجاح.');
    }

    public function submit(Request $r, string $reference)
    {
        $app = $this->authorizedApp($reference, $r);
        if (! $app->isEditableByApplicant()) {
            return back()->withErrors(['form' => 'الطلب غير قابل للإرسال الآن.']);
        }
        if (! $app->email_verified_at) {
            return back()->withErrors(['form' => 'يلزم التحقق من البريد أولًا.']);
        }
        // creator_applications.monthly.max: يُستهلَك مرة واحدة عند الإرسال (idempotent)
        $ent = app(CreatorEntitlementService::class);
        if ($org = $ent->orgForTenant($app->tenant_id)) {
            try {
                $ent->consumeSubmission($org, $app);
            } catch (EntitlementLimitExceeded) {
                return back()->withErrors(['form' => 'تم بلوغ حدّ الطلبات الشهرية لهذه الوكالة. حاول لاحقًا.']);
            }
        }
        $this->svc->transition($app, 'submitted', null, ['submitted_at' => now(), 'reason' => 'إرسال الطلب من المتقدّم']);
        $this->svc->transition($app->fresh(), 'under_review', null, ['reason' => 'بانتظار المراجعة']);

        return redirect("/join/creator/{$app->reference}/status")->with('ok', 'تم إرسال طلبك للمراجعة.');
    }

    /** يجلب الطلب مع فرض وصول آمن: جلسة المتقدّم أو رمز وصول صالح (لا يكفي المرجع). */
    private function authorizedApp(string $reference, Request $r): CreatorApplication
    {
        $app = $this->svc->findByReference($reference);
        if (! $app) {
            $this->svc->logAccessAttempt($reference, 'denied');
            abort(404);
        }
        if ($r->session()->get("app_access_{$app->id}") === true) {
            return $app;
        }
        if ($t = $r->query('t')) {
            if ($this->svc->verifyAccessToken($app, $t)) {
                $r->session()->put("app_access_{$app->id}", true); // إنشاء جلسة بعد التحقق

                return $app;
            }
            $this->svc->logAccessAttempt($reference, 'token_invalid');
        } else {
            $this->svc->logAccessAttempt($reference, 'denied');
        }
        abort(403, 'يلزم رابط وصول صالح. استعِد الوصول عبر بريدك من صفحة الاستعادة.');
    }

    /** صفحة استعادة الوصول عبر البريد (لا تكشف وجود الطلب). */
    public function recoverForm()
    {
        return view('join.recover');
    }

    public function recover(Request $r)
    {
        $data = $r->validate(['email' => 'required|email']);
        // نبحث عبر كل المستأجرين (البوابة العامة) — رسالة موحّدة مهما كانت النتيجة
        $app = TenantContext::withBypass(fn () => CreatorApplication::where('email', $data['email'])->whereNotIn('status', ['archived'])->latest('id')->first());
        $devLink = null;
        if ($app) {
            $raw = $this->svc->issueAccessToken($app);   // تدوير الرمز عند الاستعادة
            $this->svc->logAccessAttempt($app->reference, 'recovered');
            $link = "/join/creator/{$app->reference}/status?t={$raw}";
            // TODO(Queue): إرسال رابط موقّع عبر البريد. محليًا نعرضه للمعاينة فقط.
            if (! app()->environment('production')) {
                $devLink = $link;
            }
        }

        // رسالة موحّدة (منع التعداد)
        return back()->with('ok', 'إن وُجد طلب بهذا البريد، أُرسل رابط الاستعادة إليه.')->with('dev_recover_link', $devLink);
    }

    private function categories()
    {
        $c = TenantContext::withBypass(fn () => CreatorCategory::whereNull('tenant_id')->where('is_active', true)->orderBy('sort_order')->get());

        return $c;
    }
}
