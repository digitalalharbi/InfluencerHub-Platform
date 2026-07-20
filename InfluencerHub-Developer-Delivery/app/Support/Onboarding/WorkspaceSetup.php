<?php

namespace App\Support\Onboarding;

use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Creators\Models\Creator;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * قائمة تهيئة المساحة الجديدة — الخطوة التالية بدل «كل شيء تحت السيطرة».
 *
 * مساحة فارغة كانت تُخبر صاحبها ألّا شيء يحتاج تدخّله، وهو أسوأ ما يُقال لمن
 * فتح حسابه للتوّ: لا مهامّ لأن لا شيء أُنشئ بعد، لا لأن العمل منجَز.
 *
 * كل خطوة تُقاس بسجلّ فعلي في قاعدة البيانات لا بعلامة «تمّت» يضعها المستخدم،
 * فلا تُعلَن خطوة منجزة وهي ليست كذلك.
 */
class WorkspaceSetup
{
    /** تُعتبر المساحة «قيد التهيئة» ما دامت خطوة إلزامية ناقصة. */
    public static function for(int $tenantId, int $organizationId): array
    {
        // المستأجر مُمرَّر صراحةً وكل استعلام هنا يُرشِّح به، فلا حاجة للسياق
        // المحيط. بدونه كان النطاق المغلق افتراضيًّا يُرجع أصفارًا صامتة فتبدو
        // مساحة عامرة وكأنها فارغة.
        //
        // السياق يُستعاد لا يُمحى: `reset()` يمسح مستأجر الطلب الجاري فينكسر
        // كل ما يليه في الطلب نفسه.
        $steps = TenantContext::withBypass(function () use ($tenantId, $organizationId) {

        $team = OrganizationMembership::where('organization_id', $organizationId)
            ->where('status', 'active')->count();
        $clients = Client::where('tenant_id', $tenantId)->count();
        $brands = Brand::where('tenant_id', $tenantId)->count();
        $creators = Creator::where('tenant_id', $tenantId)->count();
        $requests = ServiceRequest::where('tenant_id', $tenantId)->count();
        $campaigns = Campaign::where('tenant_id', $tenantId)->count();

        // خطوة العلامة تقود إلى العميل: العلامة تُنشأ من صفحته لا من فهرس العلامات
        $firstClient = Client::where('tenant_id', $tenantId)->orderBy('id')->first();
        $brandHref = $firstClient ? "/clients/{$firstClient->id}?tab=brands" : '/clients';

        $steps = [
            [
                'key' => 'team',
                'title' => 'ادعُ فريقك',
                'why' => 'الإسناد والموافقات تحتاج أكثر من شخص واحد.',
                'done' => $team > 1,
                'count' => $team,
                'href' => '/team',
                'action' => 'دعوة عضو',
                'optional' => true,
            ],
            [
                'key' => 'client',
                'title' => 'أضِف أوّل عميل',
                'why' => 'الحملات والفواتير تُبنى على عميل.',
                'done' => $clients > 0,
                'count' => $clients,
                'href' => '/clients',
                'action' => 'إضافة عميل',
                'optional' => false,
            ],
            [
                'key' => 'brand',
                'title' => 'أضِف علامة للعميل',
                'why' => 'العلامة تحدّد هوية الحملة ومراجعها.',
                'done' => $brands > 0,
                'count' => $brands,
                'href' => $brandHref,
                'action' => $firstClient ? 'إضافة علامة' : 'ابدأ بعميل',
                'optional' => false,
            ],
            [
                'key' => 'creator',
                'title' => 'ابنِ قاعدة صنّاع المحتوى',
                'why' => 'بلا صنّاع لا ترشيح ولا تعاون.',
                'done' => $creators > 0,
                'count' => $creators,
                'href' => '/creators',
                'action' => 'إضافة صانع',
                'optional' => false,
            ],
            [
                'key' => 'request',
                'title' => 'استقبل أوّل طلب',
                'why' => 'الطلب يحمل موجز العميل فينتقل إلى الحملة بلا إعادة إدخال.',
                'done' => $requests > 0,
                'count' => $requests,
                'href' => '/requests',
                'action' => 'إنشاء طلب',
                'optional' => true,
            ],
            [
                'key' => 'campaign',
                'title' => 'أطلق أوّل حملة',
                'why' => 'هنا يبدأ التشغيل الفعلي: ترشيح، عقد، محتوى، تسوية.',
                'done' => $campaigns > 0,
                'count' => $campaigns,
                'href' => '/campaigns',
                'action' => 'إنشاء حملة',
                'optional' => false,
            ],
        ];

            return $steps;
        });

        $required = array_filter($steps, fn ($s) => ! $s['optional']);
        $doneRequired = array_filter($required, fn ($s) => $s['done']);

        return [
            'steps' => array_values($steps),
            'doneCount' => count(array_filter($steps, fn ($s) => $s['done'])),
            'total' => count($steps),
            'requiredDone' => count($doneRequired),
            'requiredTotal' => count($required),
            // تُخفى القائمة متى اكتملت الخطوات الإلزامية: التهيئة مرحلة لا واجهة دائمة
            'isSettingUp' => count($doneRequired) < count($required),
            'next' => self::firstUndone($steps),
        ];
    }

    /** @param array<int,array<string,mixed>> $steps */
    private static function firstUndone(array $steps): ?array
    {
        // الإلزامي أوّلًا: لا نوجّه إلى دعوة الفريق قبل وجود عميل
        foreach ([false, true] as $optional) {
            foreach ($steps as $s) {
                if ($s['optional'] === $optional && ! $s['done']) {
                    return ['key' => $s['key'], 'title' => $s['title'], 'href' => $s['href'], 'action' => $s['action']];
                }
            }
        }

        return null;
    }
}
