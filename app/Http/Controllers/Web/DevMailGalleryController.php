<?php

namespace App\Http\Controllers\Web;

use App\Domain\Communications\Models\Notification;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\NotificationMail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * معرض بريد للتطوير/الاختبار فقط — يعرض حالات البريد التمثيليّة ببيانات تجريبيّة آمنة،
 * بلغتَي ar/en، لفحص الجودة بصريًّا. محكوم بـconfig('app.dev_tools') (404 في الإنتاج).
 * لا مسار عامّ غير مصادَق، ولا بيانات إنتاج — كائنات إشعار في الذاكرة فقط.
 */
class DevMailGalleryController extends Controller
{
    /** حالات تمثيليّة: نسخة عربية وإنجليزية + بيانات سياق اختياريّة (تُعرَض إن وُجدت). */
    private function states(): array
    {
        return [
            'standard' => [
                'ar' => ['title' => 'نُشر محتوى «إعلان الصيف» لحملة نايك', 'body' => 'اكتمل نشر المحتوى على الحساب المتّفق عليه. لا إجراء مطلوب منك؛ هذه رسالة للعلم.'],
                'en' => ['title' => 'Content "Summer Ad" for Nike is now live', 'body' => 'The content was published to the agreed account. No action needed — this is for your awareness.'],
                'action_url' => '/app/content/123',
                'data' => ['context' => 'Nike · حملة الصيف', 'status_ar' => 'منشور', 'status_en' => 'Published'],
            ],
            'action_required' => [
                'ar' => ['title' => 'مطلوب اعتمادك على ترشيح مؤثّري حملة نايك', 'body' => 'أرسلت الوكالة قائمة ترشيح من ٥ مؤثّرين لاعتمادك. راجع الأسماء والأسعار المقترحة ثم اعتمد أو اطلب تعديلًا.'],
                'en' => ['title' => 'Your approval is needed on the Nike influencer shortlist', 'body' => 'The agency submitted a 5-influencer shortlist for your approval. Review the names and proposed rates, then approve or request changes.'],
                'action_url' => '/client/campaigns/45/shortlist',
                'data' => ['context' => 'Nike · حملة الصيف', 'status_ar' => 'بانتظار قرارك', 'status_en' => 'Awaiting your decision', 'requester' => 'وكالة ألف', 'due_ar' => '٣٠ نوفمبر ٢٠٢٦', 'due_en' => '30 Nov 2026', 'priority' => 'high'],
            ],
            'success' => [
                'ar' => ['title' => 'وقّع المبدع عقد التعاون', 'body' => 'أتمّ المبدع توقيع العقد إلكترونيًّا. يمكنك بدء مرحلة تسليم المحتوى الآن.'],
                'en' => ['title' => 'The creator signed the collaboration contract', 'body' => 'The creator has e-signed the contract. You can start the content delivery stage now.'],
                'action_url' => '/app/contracts/78',
                'data' => ['context' => 'عقد CN-78', 'status_ar' => 'سارٍ', 'status_en' => 'Active', 'priority' => 'normal'],
            ],
            'warning_overdue' => [
                'ar' => ['title' => 'تجاوز طلب خدمة موعده النهائي', 'body' => 'طلب «تعديل الموجز» تجاوز موعده ولم يُغلق بعد. يُرجى المعالجة أو إعادة التعيين.'],
                'en' => ['title' => 'A service request has passed its deadline', 'body' => 'The request "Brief revision" is past its due date and still open. Please action it or reassign.'],
                'action_url' => '/app/service-requests/12',
                'data' => ['context' => 'طلب SR-12', 'status_ar' => 'متأخّر', 'status_en' => 'Overdue', 'requester' => 'عميل نايك', 'due_ar' => '٢٠ نوفمبر ٢٠٢٦', 'due_en' => '20 Nov 2026', 'priority' => 'urgent'],
            ],
            'finance' => [
                'ar' => ['title' => 'جُدول صرف مستحقّك', 'body' => 'اعتُمد مستحقّك وجُدول للصرف. ستصلك إشعارات بحالة الدفع.'],
                'en' => ['title' => 'Your payout has been scheduled', 'body' => 'Your payout was approved and scheduled. You will be notified of the payment status.'],
                'action_url' => '/creator/payouts',
                'data' => ['context' => 'مستحق PO-90', 'status_ar' => 'مجدول', 'status_en' => 'Scheduled', 'due_ar' => '١ ديسمبر ٢٠٢٦', 'due_en' => '1 Dec 2026'],
            ],
            'creator_workflow' => [
                'ar' => ['title' => 'دعوة تعاون جديدة من وكالة ألف', 'body' => 'تلقّيت عرض تعاون لحملة «إطلاق المنتج». راجع النطاق والأجر المقترح ثم اقبل أو اعتذر.'],
                'en' => ['title' => 'New collaboration invitation from Agency A', 'body' => 'You received a collaboration offer for the "Product Launch" campaign. Review the scope and proposed fee, then accept or decline.'],
                'action_url' => '/creator/collaborations',
                'data' => ['context' => 'حملة إطلاق المنتج', 'status_ar' => 'عرض جديد', 'status_en' => 'New offer', 'requester' => 'وكالة ألف', 'priority' => 'high', 'secondary_url' => '/creator/collaborations', 'secondary_label_ar' => 'عرض كل التعاونات', 'secondary_label_en' => 'View all collaborations'],
            ],
        ];
    }

    /** يبني إشعارًا في الذاكرة لحالة بلغة، مُهيّئًا الحقول المترجَمة. */
    private function fixture(string $state, string $locale): Notification
    {
        $s = $this->states()[$state] ?? abort(404);
        $copy = $s[$locale] ?? $s['ar'];
        $d = $s['data'] ?? [];

        $data = array_filter([
            'context' => $d['context'] ?? null,
            'status' => $d['status_'.$locale] ?? ($d['status_ar'] ?? null),
            'requester' => $d['requester'] ?? null,
            'due' => $d['due_'.$locale] ?? ($d['due_ar'] ?? null),
            'priority' => $d['priority'] ?? null,
            'secondary_url' => $d['secondary_url'] ?? null,
            'secondary_label' => $d['secondary_label_'.$locale] ?? ($d['secondary_label_ar'] ?? null),
        ], fn ($v) => $v !== null);

        $n = new Notification;
        $n->title = $copy['title'];
        $n->body = $copy['body'];
        $n->action_url = $s['action_url'] ?? null;
        $n->data = $data;
        $n->category = 'general';
        $n->type = 'gallery.'.$state;

        $u = new User;
        $u->name = 'مستخدم تجريبي';
        $u->locale = $locale;
        $n->setRelation('user', $u);

        return $n;
    }

    /** صفحة المعرض — شبكة معاينات لكل حالة بلغتَيها (لقطة واحدة تُظهر النظام كلّه). */
    public function index(Request $r): Response
    {
        abort_unless(config('app.dev_tools'), 404);

        $states = array_keys($this->states());
        // مسار جذريّ نسبيّ من الطلب الحالي (يحمل بادئة التركيب /app ويعمل على أي مضيف/منفَذ).
        $base = $r->getPathInfo();
        $cards = '';
        foreach ($states as $st) {
            foreach (['ar', 'en'] as $loc) {
                $src = "{$base}/{$st}?locale={$loc}";
                $cards .= '<figure style="margin:0;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;background:#fff;">'
                    .'<figcaption style="padding:8px 12px;font:600 12px/1.4 Tahoma,Arial;background:#0b1220;color:#fff;">'
                    .e($st).' · '.strtoupper($loc).'</figcaption>'
                    .'<iframe src="'.e($src).'" style="width:100%;height:620px;border:0;display:block;" title="'.e($st.' '.$loc).'"></iframe>'
                    .'</figure>';
            }
        }

        $html = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
            .'<title>معرض البريد — تطوير</title></head>'
            .'<body style="margin:0;background:#f1f5f9;font-family:Tahoma,Arial;padding:20px;">'
            .'<h1 style="font-size:18px;color:#0b1220;">معرض بريد InfluencerHub — حالات تمثيليّة (تطوير فقط)</h1>'
            .'<p style="color:#475467;font-size:13px;">بيانات تجريبيّة آمنة · بلغتَي ar/en · القالب نفسه لكل الحالات.</p>'
            .'<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px;margin-top:16px;">'
            .$cards.'</div></body></html>';

        return response($html);
    }

    /** يعرض بريد حالة واحدة كـHTML مُصيَّر (للقطة/فحص). */
    public function show(Request $r, string $state): Response
    {
        abort_unless(config('app.dev_tools'), 404);
        $locale = in_array($r->query('locale'), ['ar', 'en'], true) ? $r->query('locale') : 'ar';

        $html = (new NotificationMail($this->fixture($state, $locale)))->render();

        return response($html);
    }
}
