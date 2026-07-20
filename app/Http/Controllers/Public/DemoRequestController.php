<?php

namespace App\Http\Controllers\Public;

use App\Domain\Onboarding\Models\DemoRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * طلب عرض توضيحي — سجلّ حقيقي لا نموذج زينة.
 *
 * الصفحة تعد بشيء واحد: أن يصل الطلب وأن يُتابَع. لذلك يُحفظ في `demo_requests`
 * ويُعاد للمستخدم مرجع يذكره عند المتابعة، تمامًا كطلب فتح الحساب. نموذج يرسل
 * إلى العدم — ولو بدا ناجحًا — كذبة على من ملأه.
 *
 * التحويل بعد الحفظ إلى صفحة المرجع (POST-Redirect-GET): تحديث الصفحة لا يعيد
 * إنشاء طلب مكرّر.
 */
class DemoRequestController extends Controller
{
    /**
     * `audience` مقيَّد بمفاتيح النموذج لا بقائمة حرّة: هذه الجهة تحدّد ما يُعرض
     * في الجلسة، وقيمة مجهولة تجعل الطلب غير قابل للتحضير.
     */
    private const RULES = [
        'contact_name' => 'required|string|max:120',
        'email' => 'required|email|max:190',
        'phone' => 'nullable|string|max:40',
        'company_name' => 'nullable|string|max:190',
        'role_title' => 'nullable|string|max:120',
        'team_size' => 'nullable|string|max:30',
        'preferred_time' => 'nullable|string|max:30',
        'interests' => 'nullable|string|max:2000',
    ];

    public function form(): Response
    {
        return Inertia::render('Public/Demo', [
            'audiences' => collect(DemoRequest::AUDIENCES)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    public function store(Request $r): RedirectResponse
    {
        $data = $r->validate([
            ...self::RULES,
            'audience' => 'required|string|in:'.implode(',', array_keys(DemoRequest::AUDIENCES)),
        ]);

        $demo = DemoRequest::create([
            ...$data,
            'status' => 'submitted',
            'ip_address' => $r->ip(),
        ]);

        return redirect("/demo/submitted/{$demo->reference}");
    }

    /**
     * المرجع وحده مفتاح الصفحة، وما يُعرض هو ما أدخله صاحب الطلب بنفسه —
     * لا بيانات إضافية تُسرَّب لمن خمّن مرجعًا.
     */
    public function submitted(string $reference): Response
    {
        $demo = DemoRequest::where('reference', $reference)->firstOrFail();

        return Inertia::render('Public/DemoSubmitted', [
            'reference' => $demo->reference,
            'email' => $demo->email,
            'audienceLabel' => $demo->audienceLabel(),
        ]);
    }
}
