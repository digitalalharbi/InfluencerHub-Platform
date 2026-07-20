<?php

namespace App\Http\Controllers\Inertia\Creator;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Creators\Models\{Creator, CreatorPlatform, CreatorPortfolio, CreatorService};
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Creators\Support\FinancialCrypto;
use App\Http\Controllers\Concerns\ManagesAccountSecurity;
use App\Http\Controllers\Controller;
use App\Support\Platforms\PlatformRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * حساب المبدع (React/Inertia) — الملف الشخصي والمنصّات والخدمات ونماذج الأعمال
 * وموثوق والبيانات المالية، في مساحة واحدة بتبويبات.
 *
 * منقول من CreatorPortalController (Blade) بنفس التحقّق والقيود:
 * - حقول محمية لا تُعدَّل من هنا (التحقّق يقبل المسموح فقط).
 * - المبدع لا يعتمد نفسه: موثوق والتحقّق المالي يعودان إلى `pending` عند التعديل.
 * - الآيبان يُشفَّر فعليًا (FinancialCrypto) ولا يُعاد إلا بآخر أربعة أرقام.
 * - الحذف يتحقّق من الملكية قبل التنفيذ (منع IDOR)، ونماذج الأعمال تُؤرشَف لا تُحذف.
 */
class AccountController extends Controller
{
    use ManagesAccountSecurity;

    protected function securityTenantId(Request $r): int
    {
        return $this->creator($r)->tenant_id;
    }

    private function creator(Request $r): Creator
    {
        return $r->attributes->get('creator');
    }

    public function index(Request $r, \App\Domain\Communications\Services\NotificationService $svc): Response
    {
        $c = $this->creator($r)->load('platforms', 'services', 'portfolios', 'capabilities');

        return Inertia::render('CreatorPortal/Account', [
            'profile' => [
                'displayName' => $c->display_name, 'professionalName' => $c->professional_name,
                'phone' => $c->phone, 'whatsapp' => $c->whatsapp, 'city' => $c->city, 'bio' => $c->bio,
                'primaryPlatform' => $c->primary_platform,
                'number' => $c->creator_number, 'type' => $c->type,
                'hasAvatar' => (bool) $c->avatar_path,
                // القدرات تُعاد كمفاتيح ليحرّرها الصانع، لا كنصّ عرض واحد
                'capabilities' => $c->capabilityKeys(),
            ],
            'capabilityOptions' => CreatorCapabilityService::options(),
            'platforms' => $c->platforms->map(fn (CreatorPlatform $p) => [
                'id' => $p->id, 'platform' => $p->platform,
                'platformLabel' => PlatformRegistry::label($p->platform),
                'handle' => $p->handle, 'url' => $p->url, 'followers' => (int) ($p->followers_count ?? 0),
            ])->values(),
            'services' => $c->services->map(fn (CreatorService $s) => [
                'id' => $s->id, 'type' => $s->service_type,
                'priceMinor' => $s->price_minor === null ? null : (int) $s->price_minor,
                'currency' => $s->currency, 'deliveryDays' => $s->delivery_days,
                'description' => $s->description, 'available' => (bool) $s->is_available,
            ])->values(),
            // المؤرشَف لا يُعرض — الأرشفة إخفاء لا حذف
            'portfolio' => $c->portfolios->where('status', 'active')->values()->map(fn (CreatorPortfolio $p) => [
                'id' => $p->id, 'type' => $p->type, 'url' => $p->url, 'category' => $p->category,
                'previousBrand' => $p->previous_brand, 'description' => $p->description,
            ]),
            'mowthooq' => [
                'licenseNumber' => $c->mowthooq_license_number,
                'expiresAt' => $c->mowthooq_expires_at?->format('Y-m-d'),
                'status' => $c->mowthooq_status,
                'statusLabel' => $this->verifyLabel($c->mowthooq_status),
            ],
            'financial' => [
                'beneficiaryName' => $c->beneficiary_name, 'bankName' => $c->bank_name,
                'ibanLast4' => $c->iban_last4, // لا يُعاد الآيبان كاملًا أبدًا
                'status' => $c->financial_verification_status,
                'statusLabel' => $this->verifyLabel($c->financial_verification_status),
            ],
            'platformOptions' => PlatformRegistry::options('creator_profile'),
            ...$this->securityPayload($r, $svc),
        ]);
    }

    public function updateProfile(Request $r): RedirectResponse
    {
        $c = $this->creator($r);
        $data = $r->validate([
            'display_name' => 'required|string|max:160',
            'professional_name' => 'nullable|string|max:160',
            'phone' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:120',
            'bio' => 'nullable|string|max:2000',
            'primary_platform' => PlatformRegistry::rule('creator_profile', false),
        ]);
        $c->update($data);
        AuditLogger::log('creator.profile_updated', $c, array_keys($data), $c->tenant_id, $r->user()->id);

        return back()->with('ok', 'تم تحديث ملفك.');
    }

    /**
     * تعديل قدرات الصانع من بوابته.
     *
     * الصانع أعلم بما يجيده، وتجميد قدراته على ما صرّح به يوم التقديم يعني أن
     * من تعلّم المونتاج بعد سنة يبقى غير مرشَّح له. الحدّ الأدنى قدرة واحدة:
     * ملفّ بلا قدرة لا يُطابق أي ترشيح، فيُصبح موجودًا وغير قابل للعثور عليه.
     */
    public function updateCapabilities(Request $r): RedirectResponse
    {
        $c = $this->creator($r);
        $data = $r->validate(CreatorCapabilityService::rules(), CreatorCapabilityService::messages());

        $caps = CreatorCapabilityService::sync($c, $data['capabilities']);
        AuditLogger::log('creator.capabilities_updated', $c, ['capabilities' => $caps], $c->tenant_id, $r->user()->id);

        return back()->with('ok', 'حُدّثت قدراتك.');
    }

    public function storePlatform(Request $r): RedirectResponse
    {
        $c = $this->creator($r);
        $data = $r->validate([
            'platform' => PlatformRegistry::rule('creator_profile'),
            'handle' => 'required|string|max:120',
            'url' => 'nullable|url|max:255',
            'followers_count' => 'nullable|integer|min:0',
        ]);
        CreatorPlatform::create($data + ['tenant_id' => $c->tenant_id, 'creator_id' => $c->id]);

        return back()->with('ok', 'أُضيفت المنصة.');
    }

    public function deletePlatform(Request $r, int $platform): RedirectResponse
    {
        $p = CreatorPlatform::where('id', $platform)->where('creator_id', $this->creator($r)->id)->first();
        abort_unless($p, 404); // منع IDOR: منصّة مبدع آخر
        $p->delete();

        return back()->with('ok', 'حُذفت المنصة.');
    }

    public function storeService(Request $r): RedirectResponse
    {
        $c = $this->creator($r);
        $data = $r->validate([
            'service_type' => 'required|string|max:30',
            'price' => 'nullable|numeric|min:0',
            'delivery_days' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:500',
        ]);
        CreatorService::create([
            'tenant_id' => $c->tenant_id, 'creator_id' => $c->id, 'service_type' => $data['service_type'],
            'price_minor' => isset($data['price']) ? (int) round($data['price'] * 100) : null,
            'currency' => 'SAR', 'delivery_days' => $data['delivery_days'] ?? null,
            'description' => $data['description'] ?? null, 'is_available' => true,
        ]);

        return back()->with('ok', 'أُضيفت الخدمة.');
    }

    public function deleteService(Request $r, int $service): RedirectResponse
    {
        $s = CreatorService::where('id', $service)->where('creator_id', $this->creator($r)->id)->first();
        abort_unless($s, 404);
        $s->delete();

        return back()->with('ok', 'حُذفت الخدمة.');
    }

    public function storePortfolio(Request $r): RedirectResponse
    {
        $c = $this->creator($r);
        $data = $r->validate([
            'type' => 'required|in:image,video,link',
            'url' => 'nullable|url|max:255',
            'category' => 'nullable|string|max:60',
            'previous_brand' => 'nullable|string|max:160',
            'description' => 'nullable|string|max:500',
        ]);
        CreatorPortfolio::create($data + ['tenant_id' => $c->tenant_id, 'creator_id' => $c->id, 'status' => 'active']);

        return back()->with('ok', 'أُضيف نموذج العمل.');
    }

    public function deletePortfolio(Request $r, int $item): RedirectResponse
    {
        $pf = CreatorPortfolio::where('id', $item)->where('creator_id', $this->creator($r)->id)->first();
        abort_unless($pf, 404);
        $pf->update(['status' => 'hidden']); // أرشفة لا حذف نهائي

        return back()->with('ok', 'أُرشِف نموذج العمل.');
    }

    public function updateMowthooq(Request $r): RedirectResponse
    {
        $c = $this->creator($r);
        $data = $r->validate([
            'mowthooq_license_number' => 'nullable|string|max:120',
            'mowthooq_expires_at' => 'nullable|date',
        ]);
        // المبدع لا يوثّق نفسه — تعود الحالة إلى الانتظار ما لم تكن موثّقة أصلًا
        $c->update($data + ['mowthooq_status' => $c->mowthooq_status === 'verified' ? 'verified' : 'pending']);

        return back()->with('ok', 'حُفظت بيانات موثوق (بانتظار مراجعة الوكالة).');
    }

    public function updateFinancial(Request $r): RedirectResponse
    {
        $c = $this->creator($r);
        $data = $r->validate([
            'beneficiary_name' => 'nullable|string|max:160',
            'bank_name' => 'nullable|string|max:120',
            'iban' => 'nullable|string|max:40',
        ]);
        $update = [
            'beneficiary_name' => $data['beneficiary_name'] ?? $c->beneficiary_name,
            'bank_name' => $data['bank_name'] ?? $c->bank_name,
        ];
        if (! empty($data['iban'])) {
            $update = array_merge($update, FinancialCrypto::encryptIban($data['iban'])); // تشفير فعلي
        }
        $update['financial_verification_status'] = $c->financial_verification_status === 'verified' ? 'verified' : 'pending';
        $c->update($update);
        AuditLogger::log('creator.financial_updated', $c, ['iban_changed' => ! empty($data['iban'])], $c->tenant_id, $r->user()->id);

        return back()->with('ok', 'حُفظت البيانات المالية (الآيبان مُشفَّر).');
    }

    public function uploadAvatar(Request $r): RedirectResponse
    {
        $c = $this->creator($r);
        $r->validate(['file' => 'required|file|max:10240']);
        $file = $r->file('file');
        $rules = config('creators.uploads.avatar');
        if (! in_array($file->getMimeType(), $rules['mimes'], true)) {
            return back()->withErrors(['file' => 'نوع صورة غير مسموح.']);
        }
        $path = $file->storeAs(
            "creators/{$c->tenant_id}/{$c->id}/avatar",
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'local', // قرص خاص — لا رابط عام ثابت
        );
        $c->update(['avatar_path' => $path]);
        AuditLogger::log('creator.avatar_uploaded', $c, [], $c->tenant_id, $r->user()->id);

        return back()->with('ok', 'حُدِّثت الصورة الشخصية.');
    }

    private function verifyLabel(?string $status): string
    {
        return match ($status) {
            'verified' => 'موثّق',
            'rejected' => 'مرفوض',
            'pending' => 'قيد التحقّق',
            default => 'غير مُقدَّم',
        };
    }
}
