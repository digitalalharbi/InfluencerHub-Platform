<?php

namespace App\Domain\Brands\Services;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandSocialAccount;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\OrganizationMembership;

/**
 * قائمة تهيئة مساحة العلامة.
 *
 * **كل خطوة تُقاس بعدّ سجلّات حقيقية**، ولا يوجد عمود «أكملتُ هذه الخطوة».
 * الفرق ليس تجميليًّا: مربّع اختيار يُعلَّم يدويًّا يكذب بمجرّد أن يُحذف ما
 * بُني عليه — فتبقى الخطوة «مكتملة» وقد زال سببها. وحين تُشتقّ من البيانات
 * تصير القائمة انعكاسًا للحال لا ذاكرةً عنه.
 *
 * ولذلك تُعاد الخطوة إلى «غير مكتملة» تلقائيًّا متى زال ما أكملها.
 */
class BrandOnboardingService
{
    /**
     * @return array{steps:array<int,array<string,mixed>>, doneCount:int, total:int, isSettingUp:bool}
     */
    public function checklist(Brand $brand, int $tenantId, int $organizationId): array
    {
        // اكتمال بيانات العلامة: الحقول التي تجعلها قابلة للعرض على مبدع
        $profileFields = ['sector', 'website', 'description'];
        $filled = collect($profileFields)->filter(fn ($f) => filled($brand->{$f}))->count();
        $hasLogo = filled($brand->logo_path);

        $team = OrganizationMembership::where('organization_id', $organizationId)
            ->where('status', 'active')->count();

        $requests = ServiceRequest::where('tenant_id', $tenantId)->count();
        $campaigns = Campaign::where('tenant_id', $tenantId)->count();

        // تفويض وكالة، أو قرار صريح بالتشغيل الذاتي (وجود حملة يثبته)
        $agencies = BrandWorkspaceRelationship::where('brand_id', $brand->id)
            ->where('relationship_type', BrandWorkspaceRelationship::MANAGING_AGENCY)
            ->where('status', 'active')->whereNull('ended_at')->count();

        $socials = BrandSocialAccount::where('brand_id', $brand->id)->count();

        $steps = [
            [
                'key' => 'profile',
                'title' => 'إكمال بيانات العلامة',
                'hint' => 'القطاع والموقع والوصف — هذه ما يقرؤه المبدع قبل قبول التعاون.',
                'done' => $filled === count($profileFields),
                'progress' => "{$filled}/".count($profileFields),
                'href' => '/brand/settings',
                'action' => 'أكمل البيانات',
                'optional' => false,
            ],
            [
                'key' => 'team',
                'title' => 'دعوة الفريق',
                'hint' => 'مساحة بعضو واحد تتعطّل بغيابه.',
                'done' => $team > 1,
                'progress' => (string) $team,
                'href' => '/brand/team',
                'action' => 'ادعُ زميلًا',
                'optional' => false,
            ],
            [
                'key' => 'first_request',
                'title' => 'تقديم أوّل طلب',
                'hint' => 'الطلب هو مدخل الحملة — منه تُشتقّ ولا تُعاد كتابته.',
                'done' => $requests > 0,
                'progress' => (string) $requests,
                'href' => '/brand/requests',
                'action' => 'قدّم طلبًا',
                'optional' => false,
            ],
            [
                'key' => 'operating_model',
                'title' => 'ربط وكالة أو التشغيل الذاتي',
                'hint' => 'فوّض وكالة بنطاق محدَّد، أو شغّل حملاتك بنفسك.',
                'done' => $agencies > 0 || $campaigns > 0,
                'progress' => $agencies > 0 ? "{$agencies} وكالة" : ($campaigns > 0 ? 'تشغيل ذاتي' : '—'),
                'href' => '/brand/agencies',
                'action' => 'اختر نموذج التشغيل',
                'optional' => false,
            ],
            [
                'key' => 'identity',
                'title' => 'رفع الهوية والملفّات',
                'hint' => 'الشعار وحسابات التواصل — يظهران للمبدعين وفي التقارير.',
                'done' => $hasLogo && $socials > 0,
                'progress' => ($hasLogo ? 'شعار' : 'بلا شعار')." · {$socials} حساب",
                'href' => '/brand/settings',
                'action' => 'ارفع الهوية',
                'optional' => true,
            ],
            [
                'key' => 'integrations',
                'title' => 'ربط التكاملات',
                'hint' => 'لا مزوّد معتمَد بعد — تُفتح هذه الخطوة حين يتوفّر.',
                'done' => false,
                'progress' => 'غير متاح',
                'href' => null,
                'action' => null,
                'optional' => true,
                // صادقة بدل أن تُعرض قابلةً للإكمال وهي ليست كذلك
                'blocked' => true,
                'blockedReason' => 'بانتظار اعتماد مزوّد رسمي.',
            ],
        ];

        $required = array_values(array_filter($steps, fn ($s) => ! $s['optional']));
        $doneRequired = array_values(array_filter($required, fn ($s) => $s['done']));

        return [
            'steps' => $steps,
            'doneCount' => count(array_filter($steps, fn ($s) => $s['done'])),
            'total' => count($steps),
            'requiredDone' => count($doneRequired),
            'requiredTotal' => count($required),
            // تُخفى القائمة متى اكتملت الإلزاميّات: التهيئة مرحلة لا واجهة دائمة
            'isSettingUp' => count($doneRequired) < count($required),
        ];
    }
}
