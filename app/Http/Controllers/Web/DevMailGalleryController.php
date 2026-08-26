<?php

namespace App\Http\Controllers\Web;

use App\Domain\Communications\Models\Notification;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\NotificationMail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * معرض بريد للتطوير/الاختبار فقط — حالات بريد تمثيليّة ببيانات تجريبيّة آمنة، بلغتَي
 * ar/en، لفحص جودة الصياغة بصريًّا. محكوم بـconfig('app.dev_tools') (404 في الإنتاج).
 * لا مسار عامّ غير مصادَق، ولا بيانات إنتاج — كائنات إشعار في الذاكرة فقط.
 */
class DevMailGalleryController extends Controller
{
    /** اسم المستقبِل التجريبيّ لكل لغة (للتحيّة الشخصيّة). */
    private const NAME = ['ar' => 'محمد العتيبي', 'en' => 'Mohammed Alotaibi'];

    /**
     * حالات تمثيليّة لأحداث حقيقيّة: عنوان/نصّ عربي أبيض + إنجليزي، كائنات أعمال بأسمائها،
     * حالة/موعد إنسانيّة، وزرّ إجراء خاصّ بالحدث. لا مصطلحات تقنيّة في نصّ المستخدم.
     */
    private function states(): array
    {
        return [
            'task_assigned' => [
                'ar' => ['title' => 'أُسندت إليك مهمة جديدة في حملة صيف الرياض', 'body' => 'أُسندت إليك مراجعة موجز الحملة وتجهيزه للانطلاق. اطّلع على التفاصيل وابدأ متى تجهز.'],
                'en' => ['title' => 'A new task was assigned to you in the Riyadh Summer campaign', 'body' => 'You have been asked to review the campaign brief and get it ready to launch. Open the details and start when you are ready.'],
                'url' => '/app/service-requests/12', 'cta' => ['ar' => 'عرض المهمة', 'en' => 'View task'],
                'objects' => [['type' => 'campaign', 'name' => 'صيف الرياض'], ['type' => 'client', 'name' => 'شركة نماء']],
                'status' => ['ar' => 'جديدة', 'en' => 'New'], 'priority' => 'normal',
            ],
            'approval_required' => [
                'ar' => ['title' => 'نحتاج موافقتك على ترشيحات حملة صيف الرياض', 'body' => 'جهّزنا لك قائمة من خمسة مؤثّرين مقترحين للحملة. اطّلع على الأسماء والأسعار، ثم اعتمد القائمة أو أرسل ملاحظاتك وسنحدّثها مباشرة.'],
                'en' => ['title' => 'We need your approval on the Riyadh Summer shortlist', 'body' => 'We prepared a shortlist of five suggested influencers for the campaign. Review the names and prices, then approve the list or send your notes and we will update it right away.'],
                'url' => '/client/campaigns/45/shortlist', 'cta' => ['ar' => 'مراجعة الترشيحات', 'en' => 'Review shortlist'],
                'objects' => [['type' => 'campaign', 'name' => 'صيف الرياض'], ['type' => 'nomination', 'name' => 'القائمة الأولى']],
                'status' => ['ar' => 'بانتظار قرارك', 'en' => 'Awaiting your decision'], 'due' => ['ar' => '٣٠ نوفمبر ٢٠٢٦', 'en' => '30 Nov 2026'], 'priority' => 'high',
            ],
            'creator_invitation' => [
                'ar' => ['title' => 'لديك دعوة جديدة للمشاركة في حملة صيف الرياض', 'body' => 'يسرّنا دعوتك للمشاركة في الحملة. اطّلع على تفاصيل المشاركة والموعد المطلوب، ثم أكّد قبولك أو اعتذارك.'],
                'en' => ['title' => 'You have a new invitation to join the Riyadh Summer campaign', 'body' => 'We would be glad to have you in this campaign. Review the participation details and the required date, then confirm or decline.'],
                'url' => '/creator/collaborations', 'cta' => ['ar' => 'عرض الدعوة', 'en' => 'View invitation'],
                'objects' => [['type' => 'campaign', 'name' => 'صيف الرياض'], ['type' => 'client', 'name' => 'شركة نماء']],
                'status' => ['ar' => 'دعوة جديدة', 'en' => 'New invitation'], 'priority' => 'high',
            ],
            'content_review' => [
                'ar' => ['title' => 'محتوى جديد بانتظار مراجعتك في حملة صيف الرياض', 'body' => 'رفع صانع المحتوى العمل المتّفق عليه للمراجعة. اطّلع عليه واعتمده أو اطلب تعديلًا.'],
                'en' => ['title' => 'New content is ready for your review in the Riyadh Summer campaign', 'body' => 'The creator uploaded the agreed work for review. Take a look and approve it or request a change.'],
                'url' => '/app/content/88', 'cta' => ['ar' => 'مراجعة المحتوى', 'en' => 'Review content'],
                'objects' => [['type' => 'campaign', 'name' => 'صيف الرياض'], ['type' => 'creator', 'name' => 'نورة القحطاني']],
                'status' => ['ar' => 'بانتظار المراجعة', 'en' => 'Pending review'], 'priority' => 'normal',
            ],
            'overdue_reminder' => [
                'ar' => ['title' => 'تذكير: طلب «تعديل الموجز» تجاوز موعده', 'body' => 'ما زال هذا الطلب مفتوحًا بعد موعده. نقترح معالجته الآن أو إعادة تعيينه لزميل متاح.'],
                'en' => ['title' => 'Reminder: the request "Brief revision" is past its date', 'body' => 'This request is still open after its date. We suggest handling it now or reassigning it to an available colleague.'],
                'url' => '/app/service-requests/12', 'cta' => ['ar' => 'فتح الطلب', 'en' => 'Open request'],
                'objects' => [['type' => 'service_request', 'name' => 'تعديل الموجز', 'ref' => 'SR-12'], ['type' => 'client', 'name' => 'شركة نماء']],
                'status' => ['ar' => 'متأخّر', 'en' => 'Overdue'], 'due' => ['ar' => '٢٠ نوفمبر ٢٠٢٦', 'en' => '20 Nov 2026'], 'priority' => 'urgent',
            ],
            'contract_action' => [
                'ar' => ['title' => 'وقّع صانع المحتوى عقد المشاركة', 'body' => 'اكتمل توقيع العقد إلكترونيًّا من الطرفين. يمكنك الآن بدء مرحلة تنفيذ المحتوى.'],
                'en' => ['title' => 'The creator signed the participation contract', 'body' => 'The contract is now e-signed by both sides. You can start the content execution stage.'],
                'url' => '/app/contracts/78', 'cta' => ['ar' => 'مراجعة العقد', 'en' => 'Review contract'],
                'objects' => [['type' => 'contract', 'name' => 'عقد المشاركة', 'ref' => 'CN-78'], ['type' => 'creator', 'name' => 'نورة القحطاني']],
                'status' => ['ar' => 'سارٍ', 'en' => 'Active'], 'priority' => 'normal',
            ],
            'invoice_action' => [
                'ar' => ['title' => 'فاتورة جديدة بانتظار مراجعتك', 'body' => 'صدرت فاتورة جديدة على حملة صيف الرياض. راجع بنودها ثم اعتمد التحصيل.'],
                'en' => ['title' => 'A new invoice is waiting for your review', 'body' => 'A new invoice was issued for the Riyadh Summer campaign. Review its items, then approve collection.'],
                'url' => '/app/invoices/33', 'cta' => ['ar' => 'عرض الفاتورة', 'en' => 'View invoice'],
                'objects' => [['type' => 'invoice', 'name' => 'فاتورة الحملة', 'ref' => 'INV-33'], ['type' => 'client', 'name' => 'شركة نماء']],
                'status' => ['ar' => 'بانتظار المراجعة', 'en' => 'Pending review'], 'due' => ['ar' => '٥ ديسمبر ٢٠٢٦', 'en' => '5 Dec 2026'], 'priority' => 'high',
            ],
            'payout_action' => [
                'ar' => ['title' => 'مستحقّاتك جاهزة للمراجعة', 'body' => 'اعتُمدت مستحقّاتك عن المشاركة الأخيرة وجُدولت للصرف. سنُطلعك على حالة الدفع أولًا بأول.'],
                'en' => ['title' => 'Your payout is ready for review', 'body' => 'Your payout for the latest participation was approved and scheduled. We will keep you posted on the payment status.'],
                'url' => '/creator/payouts', 'cta' => ['ar' => 'مراجعة المستحقات', 'en' => 'Review payout'],
                'objects' => [['type' => 'payout', 'name' => 'مستحقّات المشاركة', 'ref' => 'PO-90']],
                'status' => ['ar' => 'مجدولة', 'en' => 'Scheduled'], 'due' => ['ar' => '١ ديسمبر ٢٠٢٦', 'en' => '1 Dec 2026'], 'priority' => 'normal',
            ],
            'integration_alert' => [
                'ar' => ['title' => 'يحتاج تكامل إنستغرام إلى إعادة الربط', 'body' => 'انقطع الاتصال بحساب إنستغرام، فتوقّف جلب البيانات. أعد الربط لاستئناف التحديثات.'],
                'en' => ['title' => 'The Instagram integration needs reconnecting', 'body' => 'The connection to the Instagram account dropped, so data sync stopped. Reconnect to resume updates.'],
                'url' => '/app/integrations/instagram', 'cta' => ['ar' => 'إعادة الربط', 'en' => 'Reconnect'],
                'objects' => [['type' => 'campaign', 'name' => 'صيف الرياض']],
                'status' => ['ar' => 'يحتاج انتباهك', 'en' => 'Needs attention'], 'priority' => 'high',
            ],
            'completed' => [
                'ar' => ['title' => 'اكتمل نشر محتوى حملة صيف الرياض', 'body' => 'نُشر المحتوى على الحساب المتّفق عليه بنجاح. لا يلزمك إجراء؛ هذه رسالة للعلم.'],
                'en' => ['title' => 'The Riyadh Summer content is now published', 'body' => 'The content was published to the agreed account successfully. No action needed — this is for your awareness.'],
                'url' => '/app/content/88', 'cta' => ['ar' => 'عرض المحتوى', 'en' => 'View content'],
                'objects' => [['type' => 'campaign', 'name' => 'صيف الرياض'], ['type' => 'publication', 'name' => 'منشور إنستغرام']],
                'status' => ['ar' => 'منشور', 'en' => 'Published'], 'priority' => 'low',
            ],
        ];
    }

    /** يبني إشعارًا في الذاكرة لحالة بلغة، بكائنات أعمالها ونصوصها المترجَمة. */
    private function fixture(string $state, string $locale): Notification
    {
        $s = $this->states()[$state] ?? abort(404);
        $copy = $s[$locale] ?? $s['ar'];

        $data = array_filter([
            'objects' => $s['objects'] ?? [],
            'status' => isset($s['status']) ? ($s['status'][$locale] ?? $s['status']['ar']) : null,
            'due' => isset($s['due']) ? ($s['due'][$locale] ?? $s['due']['ar']) : null,
            'priority' => $s['priority'] ?? null,
            'cta_label' => isset($s['cta']) ? ($s['cta'][$locale] ?? $s['cta']['ar']) : null,
        ], fn ($v) => $v !== null && $v !== []);

        $n = new Notification;
        $n->title = $copy['title'];
        $n->body = $copy['body'];
        $n->action_url = $s['url'] ?? null;
        $n->data = $data;
        $n->category = 'general';
        $n->type = 'gallery.'.$state;

        $u = new User;
        $u->name = self::NAME[$locale] ?? self::NAME['ar'];
        $u->locale = $locale;
        $n->setRelation('user', $u);

        return $n;
    }

    /** صفحة المعرض — شبكة معاينات لكل حالة بلغتَيها (لقطة واحدة تُظهر النظام كلّه). */
    public function index(Request $r): Response
    {
        abort_unless(config('app.dev_tools'), 404);

        // مسار جذريّ نسبيّ من الطلب الحالي (يحمل بادئة التركيب /app ويعمل على أي مضيف/منفَذ).
        $base = $r->getPathInfo();
        $cards = '';
        foreach (array_keys($this->states()) as $st) {
            foreach (['ar', 'en'] as $loc) {
                $src = "{$base}/{$st}?locale={$loc}";
                $cards .= '<figure style="margin:0;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;background:#fff;">'
                    .'<figcaption style="padding:8px 12px;font:600 12px/1.4 Tahoma,Arial;background:#0b1220;color:#fff;">'
                    .e($st).' · '.strtoupper($loc).'</figcaption>'
                    .'<iframe src="'.e($src).'" style="width:100%;height:640px;border:0;display:block;" title="'.e($st.' '.$loc).'"></iframe>'
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
