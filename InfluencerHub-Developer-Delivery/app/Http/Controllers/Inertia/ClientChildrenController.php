<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Actions\{CreateBrand, InviteClientMember, UploadClientDocument};
use App\Domain\CRM\Models\{Client, ClientContact, CustomFieldDefinition};
use App\Domain\CRM\Services\CustomFieldService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * الوحدات الفرعية للعميل (React/Inertia) — علامات، جهات اتصال، مستندات،
 * دعوات بوابة، وحقول مخصّصة.
 *
 * منقولة من ClientChildrenWebController بنفس الصلاحيات والتحقّق والإجراءات
 * (CreateBrand/UploadClientDocument/InviteClientMember/CustomFieldService)،
 * فلا يوجد منطق مكرّر. كل دالة ترجع back() لتبقى داخل تبويب العميل.
 *
 * تنبيه الصلاحيات: ليست كلها `update` — المستندات تتطلّب manageDocuments،
 * والدعوات تتطلّب managePortal. الإبقاء على هذا الفصل مقصود.
 */
class ClientChildrenController extends Controller
{
    public function storeBrand(Request $r, Client $client, CreateBrand $action): RedirectResponse
    {
        $this->authorize('update', $client);
        $data = $r->validate([
            'name' => 'required|string|max:160',
            'sector' => 'nullable|string|max:120',
            'website' => 'nullable|string|max:200',
        ]);
        // مسوّدة داخل مسار الاعتماد لا حالة خارجه: كان الإنشاء يضع «نشِط» وهي
        // ليست من مفردات المراجعة، فلا تظهر العلامة في طابور الاعتماد ولا
        // تصير «معتمدة» أبدًا — فتبقى الحملة محجوبة بسبب لا يُمكن رفعه.
        app(\App\Domain\CRM\Services\BrandWorkflowService::class)
            ->createDraft((int) $client->tenant_id, (int) $client->id, $data, (int) $r->user()->id);

        return back()->with('ok', 'أُضيفت العلامة كمسوّدة. الخطوة التالية: إرسالها للاعتماد.');
    }

    public function storeContact(Request $r, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $data = $r->validate([
            'name' => 'required|string|max:160',
            'job_title' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:160',
            'phone' => 'nullable|string|max:30',
        ]);
        ClientContact::create($data + ['tenant_id' => $client->tenant_id, 'client_id' => $client->id]);
        AuditLogger::log('client_contact.created', $client, [], $client->tenant_id, $r->user()->id);

        return back()->with('ok', 'تمت إضافة جهة الاتصال.');
    }

    public function storeDocument(Request $r, Client $client, UploadClientDocument $action): RedirectResponse
    {
        $this->authorize('manageDocuments', $client);
        $r->validate([
            'file' => 'required|file|max:20480',
            'category' => 'required|string',
            'title' => 'required|string|max:200',
        ]);

        try {
            $action->handle($client, $r->file('file'), $r->input('category'), $r->input('title'), $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('ok', 'تم رفع المستند.');
    }

    public function inviteMember(Request $r, Client $client, InviteClientMember $action): RedirectResponse
    {
        $this->authorize('managePortal', $client);
        $data = $r->validate(['email' => 'required|email|max:160', 'role' => 'required|string']);

        try {
            [, $raw] = $action->handle($client, $data['email'], $data['role'], $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        // الرمز يُعرض مرة واحدة فقط — لا يُخزَّن خامًا ولا يُعاد بعد تحديث الصفحة
        return back()->with('ok', 'أُرسلت الدعوة.')->with('invite_token', $raw);
    }

    public function defineField(Request $r, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $data = $r->validate([
            'key' => 'required|string|max:60',
            'label' => 'required|string|max:160',
            'type' => 'required|in:text,textarea,number,date,datetime,boolean,select,multiselect,url,email,phone',
        ]);
        CustomFieldDefinition::firstOrCreate(
            ['tenant_id' => $client->tenant_id, 'entity_type' => 'client', 'key' => $data['key']],
            ['label' => $data['label'], 'type' => $data['type']],
        );

        return back()->with('ok', 'تم تعريف الحقل المخصّص.');
    }

    public function setField(Request $r, Client $client, CustomFieldDefinition $definition, CustomFieldService $svc): RedirectResponse
    {
        $this->authorize('update', $client);
        // التعريف يجب أن يخصّ مستأجر العميل نفسه ونوعه — يمنع ضبط حقل عائد لكيان آخر
        abort_unless($definition->tenant_id === $client->tenant_id && $definition->entity_type === 'client', 404);

        try {
            $svc->setValue($definition, $client, $r->input('value'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['value' => $e->getMessage()]);
        }

        return back()->with('ok', 'تم حفظ القيمة.');
    }
}
