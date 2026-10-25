<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Models\Client;
use App\Domain\Integrations\Google\GoogleNotConfiguredException;
use App\Domain\Integrations\Google\GoogleScopes;
use App\Domain\Integrations\Google\GoogleWorkspaceAdapter;
use App\Domain\Integrations\Services\OAuthFlowService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * دورة تفويض Google (GOOGLE-01): بدء (redirect إلى شاشة موافقة Google بنطاقات القدرات المطلوبة)
 * وعودة (استبدال الرمز وتخزين الاتّصال). إجراء إداريّ (authorize create Client). لا اعتماد ⇒
 * رسالة صريحة لا تعطّل الصفحة. لا يُعرض «متّصل» دون جولة تفويض حقيقيّة.
 */
class GoogleOAuthController extends Controller
{
    public function __construct(private OAuthFlowService $oauth) {}

    /** يبدأ التفويض ويحوّل المستخدم إلى Google. القدرات من الطلب (افتراضها القدرات الأربع). */
    public function connect(Request $r): RedirectResponse
    {
        $this->authorize('create', Client::class);

        $caps = $r->input('capabilities');
        $caps = is_array($caps) && $caps !== []
            ? array_values(array_intersect($caps, GoogleScopes::ALL_CAPABILITIES))
            : [GoogleScopes::CAP_DRIVE, GoogleScopes::CAP_SHEETS, GoogleScopes::CAP_GMAIL, GoogleScopes::CAP_CALENDAR];

        try {
            $begin = $this->oauth->begin(
                (int) TenantContext::tenantId(),
                GoogleWorkspaceAdapter::PROVIDER,
                (string) config('services.google.redirect'),
                $caps,
            );
        } catch (GoogleNotConfiguredException) {
            return redirect('/app/integrations')->withErrors([
                'google' => 'تكامل Google غير مُهيّأ بعد — بانتظار بيانات اعتماد من المالك.',
            ]);
        }

        return redirect()->away($begin['url']);
    }

    /** عودة Google: يتحقّق من state ويبادل الرمز ويخزّن الاتّصال، ثمّ يعود للوحة التكاملات. */
    public function callback(Request $r): RedirectResponse
    {
        $this->authorize('create', Client::class);

        if ($r->filled('error')) {
            return redirect('/app/integrations')->withErrors(['google' => 'أُلغيت موافقة Google.']);
        }
        $data = $r->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);

        try {
            $this->oauth->complete($data['state'], $data['code']);
        } catch (RuntimeException) {
            return redirect('/app/integrations')->withErrors(['google' => 'تعذّر إكمال ربط Google — أعد المحاولة.']);
        }

        return redirect('/app/integrations')->with('ok', 'تم ربط Google بنجاح.');
    }
}
