<?php
namespace App\Domain\Analytics\Services;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Contracts\Models\Contract;
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Finance\Models\Payout;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * تجميعات تقارير حقيقية من بيانات المستأجر (لا أرقام وهمية). كلّها مقيّدة بالسياق الحالي.
 */
class AnalyticsService
{
    /** لوحة تقارير الوكالة — أرقام فعلية مجمّعة. يفترض ضبط سياق المستأجر مسبقًا (middleware). */
    public function agencyOverview(): array
    {
        return [
            'clients' => [
                'total' => Client::count(),
                'active' => Client::where('status', 'active')->count(),
            ],
            'creators' => [
                'total' => Creator::count(),
                'active' => Creator::where('status', 'active')->count(),
                // التوزيع بالقدرة لا بالنوع: من يجمع النشر وUGC كان يُحسب في دلو
                // «both» واحد، فلا يظهر في عدّ المؤثّرين ولا في عدّ صنّاع UGC.
                // المجموع هنا قد يتجاوز عدد الصنّاع عمدًا — القدرات تتقاطع.
                //
                // العدّ يمرّ بـ filter() لا بتجميع مباشر على جدول القدرات: التجميع
                // المباشر يُسقط صامتًا كل صانع لم تُنقل قدراته، فتُظهر لوحة التقارير
                // عددًا أقلّ من الحقيقة دون أن يشتكي شيء.
                'by_capability' => collect(CreatorCapabilityService::keys())
                    ->mapWithKeys(fn (string $k) => [$k => CreatorCapabilityService::filter(Creator::query(), $k)->count()])
                    ->filter()
                    ->all(),
            ],
            'requests' => [
                'open' => ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)->count(),
                'overdue' => ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'by_status' => ServiceRequest::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->all(),
            ],
            'campaigns' => [
                'total' => Campaign::count(),
                'active' => Campaign::where('status', 'active')->count(),
                'budget_minor' => (int) Campaign::sum('budget_minor'),
                'by_status' => Campaign::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->all(),
            ],
            'collaborations' => [
                'total' => Collaboration::count(),
                'by_status' => Collaboration::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->all(),
            ],
            'content' => [
                'total' => ContentItem::count(),
                'published' => ContentItem::where('status', 'published')->count(),
                'awaiting' => ContentItem::whereIn('status', ['submitted', 'agency_review', 'client_review'])->count(),
                'by_status' => ContentItem::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->all(),
            ],
            'contracts' => [
                'active' => Contract::where('status', 'active')->count(),
                'value_active_minor' => (int) Contract::where('status', 'active')->sum('value_minor'),
            ],
            'payouts' => [
                'paid_minor' => (int) Payout::where('status', 'paid')->sum('amount_minor'),
                'open_minor' => (int) Payout::whereIn('status', Payout::OPEN)->sum('amount_minor'),
            ],
        ];
    }
}
