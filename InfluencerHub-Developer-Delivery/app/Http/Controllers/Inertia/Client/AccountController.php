<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\Communications\Models\NotificationPreference;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\CRM\Actions\ClientAddressActions;
use App\Domain\CRM\Models\{Client, ClientAddress, ClientBillingProfile, ClientProfileChangeRequest};
use App\Domain\CRM\Services\ClientProfileService;
use App\Domain\CRM\Support\ClientPortalAbilities;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Concerns\ManagesAccountSecurity;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * حساب العميل (React/Inertia) — الملف والفوترة والعناوين والإعدادات بتبويبات.
 *
 * منقول من ClientPortalController (Blade) بنفس القدرات والقيود:
 * - القدرات دور-بدور عبر ClientPortalAbilities (تحرير الملف/الفوترة منفصلتان).
 * - الحقول الحسّاسة في الملف لا تُطبَّق مباشرة: ClientProfileService يحوّلها
 *   إلى طلب تغيير يراجعه فريق الوكالة.
 * - تغيير كلمة المرور يُبطل الجلسات الأخرى (أمان لا تجميل).
 */
class AccountController extends Controller
{
    use ManagesAccountSecurity;

    protected function securityTenantId(Request $r): int
    {
        return $this->client($r)->tenant_id;
    }

    private function client(Request $r): Client
    {
        return $r->attributes->get('activeClient');
    }

    private function role(Request $r): string
    {
        return $r->attributes->get('clientMembership')->role;
    }

    private function canEditProfile(Request $r): bool
    {
        return ClientPortalAbilities::can($this->role($r), ClientPortalAbilities::EDIT_PROFILE);
    }

    public function index(Request $r, NotificationService $svc): Response
    {
        $c = $this->client($r);
        $role = $this->role($r);
        $canBilling = ClientPortalAbilities::can($role, ClientPortalAbilities::EDIT_BILLING);
        $canViewBilling = $canBilling || $role === 'client_report_viewer';

        try {
            $addresses = ClientAddress::where('client_id', $c->id)
                ->orderByDesc('is_default')->orderBy('type')->get();
            $pending = ClientProfileChangeRequest::where('client_id', $c->id)
                ->whereIn('status', ['submitted', 'under_review', 'changes_requested'])->latest()->get();
            $bp = $canViewBilling
                ? ClientBillingProfile::firstOrCreate(['client_id' => $c->id], ['tenant_id' => $c->tenant_id])
                : null;
        } finally {
        }

        return Inertia::render('ClientPortal/Account', [
            'client' => [
                'name' => $c->display_name, 'number' => $c->client_number,
                'sector' => $c->sector, 'website' => $c->website, 'email' => $c->email,
                'phone' => $c->phone, 'whatsapp' => $c->whatsapp,
                'country' => $c->country_code, 'city' => $c->city, 'address' => $c->address,
                'preferredLanguage' => $c->preferred_language,
                'legalName' => $c->legal_name,
                'cr' => $c->commercial_registration_number,
                'crExpiry' => $c->commercial_registration_expiry?->format('Y-m-d'),
                'tax' => $c->tax_number, 'vatRegistered' => (bool) $c->vat_registered,
                'hasLogo' => (bool) $c->logo_path,
            ],
            // طلبات تغيير معلّقة على حقول حسّاسة — يعرفها المستخدم بدل أن يظنّ التعديل ضاع
            'pendingChanges' => $pending->map(fn (ClientProfileChangeRequest $p) => [
                'fields' => array_keys($p->changes ?? []),
                'status' => $p->status,
                'statusLabel' => __('statuses.' . $p->status),
                'statusTone' => __('statuses.tone.' . $p->status),
                'at' => $p->created_at?->format('Y-m-d H:i'),
            ])->values(),
            'billing' => $bp ? [
                'billingName' => $bp->billing_name, 'billingEmail' => $bp->billing_email,
                'contactName' => $bp->billing_contact_name, 'contactPhone' => $bp->billing_contact_phone,
                'taxNumber' => $bp->tax_number, 'vatRegistered' => (bool) $bp->vat_registered,
                'address' => $bp->billing_address, 'poRequired' => (bool) $bp->purchase_order_required,
                'currency' => $bp->default_currency, 'invoiceNotes' => $bp->invoice_notes,
                'paymentTermsDays' => $bp->payment_terms_days,
            ] : null,
            'addresses' => $addresses->map(fn (ClientAddress $a) => [
                'id' => $a->id, 'type' => $a->type, 'typeLabel' => self::ADDRESS_TYPE[$a->type] ?? $a->type,
                'label' => $a->label, 'recipient' => $a->recipient_name, 'phone' => $a->phone,
                'country' => $a->country_code, 'region' => $a->region, 'city' => $a->city,
                'district' => $a->district, 'street' => $a->street,
                'buildingNumber' => $a->building_number, 'postalCode' => $a->postal_code,
                'additionalNumber' => $a->additional_number,
                'isDefault' => (bool) $a->is_default, 'archived' => $a->archived_at !== null,
            ])->values(),
            'addressTypes' => self::ADDRESS_TYPE,
            ...$this->securityPayload($r, $svc),
            'can' => [
                'editProfile' => $this->canEditProfile($r),
                'editBilling' => $canBilling,
                'viewBilling' => $canViewBilling,
            ],
        ]);
    }

    private const ADDRESS_TYPE = [
        'headquarters' => 'المقر الرئيسي', 'billing' => 'الفوترة', 'shipping' => 'الشحن',
        'branch' => 'فرع', 'other' => 'أخرى',
    ];

    public function updateProfile(Request $r, ClientProfileService $svc): RedirectResponse
    {
        $c = $this->client($r);
        $data = $r->validate([
            'display_name' => 'required|string|max:200', 'sector' => 'nullable|string|max:120',
            'website' => 'nullable|string|max:200', 'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:30', 'whatsapp' => 'nullable|string|max:30',
            'country_code' => 'nullable|string|size:2', 'city' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:300', 'preferred_language' => 'nullable|string|max:10',
            // حسّاسة — تمرّ عبر طلب تغيير لا تُطبَّق مباشرة
            'legal_name' => 'nullable|string|max:200',
            'commercial_registration_number' => 'nullable|string|max:30',
            'commercial_registration_expiry' => 'nullable|date',
            'tax_number' => 'nullable|string|max:30', 'vat_registered' => 'nullable|boolean',
        ]);

        try {
            $msg = $svc->applyProfileUpdate($c, $data, $this->role($r), $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()]); // نفس مفتاح نسخة Blade
        }

        return back()->with('ok', $msg);
    }

    public function uploadLogo(Request $r): RedirectResponse
    {
        $c = $this->client($r);
        abort_unless($this->canEditProfile($r), 403);
        $r->validate(['file' => 'required|file|max:5120|mimetypes:image/png,image/jpeg,image/webp']);
        $path = $r->file('file')->storeAs(
            "clients/{$c->tenant_id}/{$c->id}/logo",
            Str::uuid() . '.' . strtolower($r->file('file')->getClientOriginalExtension()),
            'local', // قرص خاص — لا رابط عام ثابت
        );
        try {
            $c->update(['logo_path' => $path]);
        } finally {
        }

        return back()->with('ok', 'حُدِّث الشعار.');
    }

    public function updateBilling(Request $r): RedirectResponse
    {
        $c = $this->client($r);
        abort_unless(ClientPortalAbilities::can($this->role($r), ClientPortalAbilities::EDIT_BILLING), 403);
        $data = $r->validate([
            'billing_name' => 'nullable|string|max:200', 'billing_email' => 'nullable|email|max:200',
            'billing_contact_name' => 'nullable|string|max:160', 'billing_contact_phone' => 'nullable|string|max:30',
            'tax_number' => 'nullable|string|max:30', 'vat_registered' => 'nullable|boolean',
            'billing_address' => 'nullable|string|max:300', 'purchase_order_required' => 'nullable|boolean',
            'default_currency' => 'nullable|string|size:3', 'invoice_notes' => 'nullable|string|max:1000',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
        ]);

        try {
            ClientBillingProfile::firstOrCreate(['client_id' => $c->id], ['tenant_id' => $c->tenant_id])->update($data);
        } finally {
        }

        return back()->with('ok', 'حُفظ ملف الفوترة.');
    }

    /* ===== العناوين ===== */

    private function addressRules(): array
    {
        return [
            'type' => 'required|in:headquarters,billing,shipping,branch,other',
            'label' => 'nullable|string|max:120', 'recipient_name' => 'nullable|string|max:160',
            'phone' => 'nullable|string|max:30', 'country_code' => 'nullable|string|size:2',
            'region' => 'nullable|string|max:120', 'city' => 'nullable|string|max:120',
            'district' => 'nullable|string|max:120', 'street' => 'nullable|string|max:255',
            'building_number' => 'nullable|string|max:20', 'postal_code' => 'nullable|string|max:20',
            'additional_number' => 'nullable|string|max:20', 'is_default' => 'nullable|boolean',
        ];
    }

    /** العنوان يجب أن يخصّ العميل النشط — منع IDOR. */
    private function addr(Request $r, int $id): ClientAddress
    {
        $client = $this->client($r);
        $a = TenantContext::withTenant(
            $client->tenant_id,
            fn () => ClientAddress::where('id', $id)->where('client_id', $client->id)->first(),
        );
        abort_unless($a, 404);

        return $a;
    }

    public function storeAddress(Request $r, ClientAddressActions $act): RedirectResponse
    {
        abort_unless($this->canEditProfile($r), 403);
        $act->create($this->client($r), $r->validate($this->addressRules()), $r->user()->id);

        return back()->with('ok', 'أُضيف العنوان.');
    }

    public function updateAddress(Request $r, int $address, ClientAddressActions $act): RedirectResponse
    {
        abort_unless($this->canEditProfile($r), 403);
        $act->update($this->addr($r, $address), $r->validate($this->addressRules()), $r->user()->id);

        return back()->with('ok', 'حُدِّث العنوان.');
    }

    public function setDefaultAddress(Request $r, int $address, ClientAddressActions $act): RedirectResponse
    {
        abort_unless($this->canEditProfile($r), 403);
        try {
            $act->setDefault($this->addr($r, $address), $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['address' => $e->getMessage()]);
        }

        return back()->with('ok', 'عُيِّن العنوان الافتراضي.');
    }

    public function archiveAddress(Request $r, int $address, ClientAddressActions $act): RedirectResponse
    {
        abort_unless($this->canEditProfile($r), 403);
        $act->archive($this->addr($r, $address), $r->user()->id);

        return back()->with('ok', 'أُرشِف العنوان.');
    }

    public function restoreAddress(Request $r, int $address, ClientAddressActions $act): RedirectResponse
    {
        abort_unless($this->canEditProfile($r), 403);
        $act->restore($this->addr($r, $address), $r->user()->id);

        return back()->with('ok', 'استُعيد العنوان.');
    }

    /* ===== الإعدادات ===== */




}
