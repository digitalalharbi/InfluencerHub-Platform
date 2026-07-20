<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Identity\Models\User;
use App\Domain\CRM\Models\{Client, ClientDocument, CustomFieldValue};
use App\Domain\Finance\Models\Payout;
use App\Domain\Requests\Models\ServiceRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Support\Analytics\ClientAnalytics;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تفاصيل العميل (React/Inertia) — Relationship Workspace: مؤشرات مالية + الخطوة التالية/المخاطر +
 * تبويبات (الحملات/العلامات/المحتوى/العقود/المالية/الفريق). Policy(view)، معزولة، IDOR-safe.
 */
class ClientDetailController extends Controller
{
    /** أدوار أعضاء بوابة العميل بالعربية — لا تُعرض مفاتيح داخلية. */
    private const CLIENT_ROLE = [
        'client_admin' => 'مدير', 'client_finance' => 'مالية', 'client_campaign_manager' => 'مدير حملات',
        'client_content_reviewer' => 'مراجع محتوى', 'client_viewer' => 'مُطّلع',
    ];

    private const CLIENT_STATUS = [
        'lead' => ['مهتم', 'submitted'], 'qualified' => ['مؤهّل', 'under_review'], 'active' => ['نشط', 'active'],
        'inactive' => ['غير نشط', 'archived'], 'suspended' => ['موقوف', 'rejected'], 'archived' => ['مؤرشف', 'archived'],
    ];

    public function show(Request $r, Client $client): Response
    {
        $this->authorize('view', $client);
        $client->load(['brands', 'contacts', 'members.user', 'accountManager']);

        $metrics = ClientAnalytics::forClient($client);
        $campaigns = $client->campaigns()->with('brand')->withCount('deliverables')->latest()->get();
        $requests = $client->serviceRequests()->latest()->get();
        $content = $client->contentItems()->with('creator')->latest()->get();
        $contracts = $client->contracts()->with('creator')->latest()->get();
        $payouts = Payout::whereIn('campaign_id', $campaigns->pluck('id'))->with('creator')->latest()->get();

        $open = ServiceRequest::OPEN_STATUSES;
        $risks = [];
        $overdue = $requests->filter(fn ($r) => $r->sla_breached_at && in_array($r->status, $open, true))->count();
        if ($overdue > 0) $risks[] = ['label' => "$overdue طلب متأخر عن SLA", 'tone' => 'danger', 'href' => '/service-requests', 'tab' => 'requests'];
        $awaiting = $content->where('status', 'client_review')->count();
        if ($awaiting > 0) $risks[] = ['label' => "$awaiting محتوى بانتظار العميل", 'tone' => 'warning', 'href' => '/content', 'tab' => 'content'];
        $unsigned = $contracts->where('status', 'sent')->count();
        if ($unsigned > 0) $risks[] = ['label' => "$unsigned عقد بانتظار التوقيع", 'tone' => 'info', 'href' => '/contracts', 'tab' => 'contracts'];
        $ready = $payouts->whereIn('status', ['approved', 'scheduled'])->count();
        if ($ready > 0) $risks[] = ['label' => "$ready مستحق جاهز للصرف", 'tone' => 'primary', 'href' => '/payouts', 'tab' => 'finance'];

        // الخطوة التالية — أهم إجراء واحد مشتق من المخاطر بترتيب الإلحاح
        $nextAction = $risks[0] ?? null;

        [$stLabel, $stTone] = self::CLIENT_STATUS[$client->status] ?? [$client->status, 'draft'];
        $st = fn ($s) => __('statuses.' . $s);
        $tone = fn ($s) => __('statuses.tone.' . $s);

        // أسماء المستخدمين للمسؤولين (استعلام واحد) + بُرد بوابة العميل لمعرفة من له وصول
        $userNames = User::whereIn('id', $requests->pluck('assigned_to')->filter()->unique())->pluck('name', 'id');
        $portalEmails = $client->members->map(fn ($m) => mb_strtolower((string) $m->user?->email))->filter();

        // فهارس مشتركة لتغذية العروض المختلفة (استعلام واحد لكل نوع)
        $allCollabs = Collaboration::whereIn('campaign_id', $campaigns->pluck('id'))->with('creator')->get();
        $collabsByCampaign = $allCollabs->groupBy('campaign_id');
        $contentByCampaign = $content->groupBy('campaign_id');
        $payoutsByCampaign = $payouts->groupBy('campaign_id');
        $campaignNames = $campaigns->pluck('name', 'id');

        // المؤثرون: بطاقات علاقة غنية — آخر تعاون، القيمة، الأداء، حالة العلاقة
        $creators = $allCollabs->groupBy('creator_id')->map(function ($group) use ($content) {
            $cr = $group->first()->creator;
            $last = $group->max('updated_at');
            $done = $group->whereIn('status', ['completed'])->count();
            $creatorContent = $content->where('creator_id', $group->first()->creator_id);
            $published = $creatorContent->where('status', 'published')->count();
            $active = $group->whereIn('status', ['accepted', 'in_progress', 'submitted'])->count();
            $daysSince = $last ? $last->diffInDays(now()) : null;
            return [
                'id' => (int) $group->first()->creator_id,
                'name' => $cr?->display_name ?? '—',
                'handle' => $cr?->handle,
                'platform' => $cr?->primary_platform,
                'followers' => (int) ($cr?->followers_count ?? 0),
                'verified' => $cr?->mowthooq_status === 'verified',
                'collaborations' => $group->count(),
                'completed' => $done,
                'active' => $active,
                'feeMinor' => (int) $group->sum('fee_minor'),
                'content' => $creatorContent->count(),
                'published' => $published,
                // جودة التعاون: نسبة المكتمل من إجمالي التعاونات (رقم حقيقي لا تقدير)
                'quality' => $group->count() ? (int) round($done / $group->count() * 100) : 0,
                'lastAt' => $last?->format('Y-m-d'),
                'daysSince' => $daysSince,
                // تصنيف العلاقة من بيانات فعلية
                'relation' => $active > 0 ? 'active' : (($daysSince !== null && $daysSince <= 90) ? 'recent' : 'dormant'),
            ];
        })->sortByDesc('feeMinor')->values();

        $documents = ClientDocument::where('client_id', $client->id)->latest()->take(20)->get()->map(fn ($d) => [
            'id' => $d->id, 'title' => $d->title ?: $d->original_name, 'category' => $d->category,
            'status' => $d->status, 'statusLabel' => $st($d->status), 'statusTone' => $tone($d->status),
            'sizeKb' => (int) round(((int) $d->size_bytes) / 1024),
            'expiresAt' => $d->expires_at?->format('Y-m-d'),
            'expiringSoon' => (bool) ($d->expires_at && $d->expires_at->isFuture() && $d->expires_at->diffInDays(now()) <= 30),
            'expired' => (bool) ($d->expires_at && $d->expires_at->isPast()),
            'pending' => $d->status === 'pending',
        ])->values();

        $customFields = CustomFieldValue::with('definition')->where('entity_type', 'client')->where('entity_id', $client->id)->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->definition?->label ?? $v->definition?->key ?? '—',
                'type' => $v->definition?->type,
                'required' => (bool) ($v->definition?->is_required),
                'value' => is_array($v->value) ? implode('، ', $v->value) : (string) $v->value,
            ])->values();

        // آخر نشاط — مجمّع من طوابع زمنية حقيقية عبر الوحدات (لا سجل مصطنع)
        $activity = collect()
            ->concat($campaigns->take(6)->map(fn ($c) => ['at' => $c->created_at, 'icon' => 'megaphone', 'text' => "حملة: {$c->name}", 'href' => "/campaigns/{$c->id}"]))
            ->concat($content->take(6)->map(fn ($c) => ['at' => $c->updated_at, 'icon' => 'image', 'text' => "محتوى: {$c->title} · " . $st($c->status), 'href' => "/content/{$c->id}"]))
            ->concat($contracts->take(6)->map(fn ($c) => ['at' => $c->updated_at, 'icon' => 'file-text', 'text' => "عقد: {$c->title} · " . $st($c->status), 'href' => "/contracts/{$c->id}"]))
            ->concat($requests->take(6)->map(fn ($q) => ['at' => $q->created_at, 'icon' => 'inbox', 'text' => "طلب: {$q->title} · " . $st($q->status), 'href' => "/service-requests/{$q->id}"]))
            ->filter(fn ($a) => $a['at'] !== null)
            ->sortByDesc('at')->take(8)
            ->map(fn ($a) => ['icon' => $a['icon'], 'text' => $a['text'], 'href' => $a['href'], 'at' => $a['at']->format('Y-m-d')])
            ->values();

        // قدرات الإجراءات الفرعية — ثلاث بوابات مختلفة لا واحدة، فتُعرض كل أداة بحسبها
        $user = $r->user();
        $can = [
            'update' => $user->can('update', $client),
            'documents' => $user->can('manageDocuments', $client),
            'portal' => $user->can('managePortal', $client),
        ];

        return Inertia::render('Clients/Show', [
            'can' => $can,
            'fieldDefinitions' => $can['update']
                ? \App\Domain\CRM\Models\CustomFieldDefinition::where('tenant_id', $client->tenant_id)
                    ->where('entity_type', 'client')->orderBy('label')
                    ->get(['id', 'key', 'label', 'type'])
                : [],
            'client' => [
                'id' => $client->id, 'name' => $client->display_name, 'number' => $client->client_number,
                'sector' => $client->sector, 'status' => $client->status, 'statusLabel' => $stLabel, 'statusTone' => $stTone,
                'manager' => $client->accountManager?->name, 'email' => $client->email, 'phone' => $client->phone,
                'website' => $client->website, 'city' => $client->city, 'cr' => $client->commercial_registration_number,
                'tax' => $client->tax_number, 'isVip' => (bool) ($metrics['is_vip'] ?? false),
            ],
            'metrics' => [
                'revenueMinor' => (int) ($metrics['revenue_minor'] ?? 0),
                'costMinor' => (int) ($metrics['cost_minor'] ?? 0),
                'profitMinor' => (int) ($metrics['profit_minor'] ?? 0),
                'margin' => (int) ($metrics['margin'] ?? 0),
                'campaigns' => (int) ($metrics['campaigns'] ?? 0),
                'activeCampaigns' => (int) ($metrics['active_campaigns'] ?? 0),
                'creators' => (int) ($metrics['creators'] ?? 0),
                'receivableMinor' => (int) ($metrics['receivable_minor'] ?? 0),
                'pendingPayouts' => (int) ($metrics['pending_payouts'] ?? 0),
                'completion' => (int) ($metrics['completion'] ?? 0),
            ],
            'risks' => $risks,
            // Pipeline: كل حملة بمرحلتها وصحّتها وتقدّمها وعملها المرتبط
            'campaigns' => $campaigns->map(function ($c) use ($st, $tone, $contentByCampaign, $collabsByCampaign, $payoutsByCampaign) {
                $cContent = $contentByCampaign[$c->id] ?? collect();
                $cCollabs = $collabsByCampaign[$c->id] ?? collect();
                $committed = (int) $cCollabs->sum('fee_minor');
                $budget = (int) $c->budget_minor;
                $published = $cContent->where('status', 'published')->count();
                $total = max(1, $cContent->count());
                $late = $c->end_date && $c->end_date->isPast() && ! in_array($c->status, ['completed', 'cancelled'], true);
                $awaiting = $cContent->whereIn('status', ['agency_review', 'client_review'])->count();
                return [
                    'id' => $c->id, 'name' => $c->name, 'brand' => $c->brand?->name,
                    'deliverables' => (int) $c->deliverables_count,
                    'budgetMinor' => $budget, 'committedMinor' => $committed,
                    'budgetPct' => $budget > 0 ? (int) round(min(100, $committed / $budget * 100)) : 0,
                    'overBudget' => $budget > 0 && $committed > $budget,
                    'creators' => $cCollabs->pluck('creator_id')->unique()->count(),
                    'content' => $cContent->count(), 'contentPublished' => $published,
                    'progress' => $cContent->count() ? (int) round($published / $total * 100) : 0,
                    'awaiting' => $awaiting,
                    'payouts' => ($payoutsByCampaign[$c->id] ?? collect())->count(),
                    'late' => $late,
                    'startDate' => $c->start_date?->format('Y-m-d'), 'endDate' => $c->end_date?->format('Y-m-d'),
                    'status' => $c->status, 'statusLabel' => $st($c->status), 'statusTone' => $tone($c->status),
                    'stage' => in_array($c->status, ['draft', 'planning'], true) ? 'planning'
                        : (in_array($c->status, ['completed', 'cancelled'], true) ? 'closed' : 'running'),
                    'risk' => $late ? 'متأخرة عن موعدها' : ($budget > 0 && $committed > $budget ? 'تجاوز الميزانية' : ($awaiting > 0 ? "$awaiting محتوى بانتظار مراجعة" : null)),
                ];
            })->values(),
            'brands' => $client->brands->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name, 'status' => $b->status, 'statusLabel' => $st($b->status), 'statusTone' => $tone($b->status),
            ])->values(),
            // Contact workspace: القنوات + جهة أساسية + صلاحية البوابة
            'contacts' => $client->contacts->map(fn ($c) => [
                'name' => $c->name, 'role' => $c->job_title, 'department' => $c->department,
                'email' => $c->email, 'phone' => $c->phone, 'whatsapp' => $c->whatsapp,
                'isPrimary' => (bool) $c->is_primary,
                'preferredChannel' => ['email' => 'البريد', 'phone' => 'الهاتف', 'whatsapp' => 'واتساب'][$c->preferred_channel] ?? $c->preferred_channel,
                'hasPortal' => $portalEmails->contains(mb_strtolower((string) $c->email)),
            ])->sortByDesc('isPrimary')->values(),
            'team' => $client->members->map(fn ($m) => [
                'name' => $m->user?->name ?? '—',
                'role' => self::CLIENT_ROLE[$m->role] ?? $m->role,
                'status' => $st($m->status), 'statusTone' => $tone($m->status),
            ])->values(),
            // Gallery: معاينة + إصدار + مرحلة + موعد + إجراء مطلوب
            'content' => $content->take(24)->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'creator' => $c->creator?->display_name,
                'platform' => $c->platform, 'type' => $c->type,
                'mediaUrl' => $c->media_url, 'caption' => $c->caption ? mb_substr($c->caption, 0, 90) : null,
                'version' => (int) $c->version,
                'campaign' => $campaignNames[$c->campaign_id] ?? null,
                'scheduledAt' => $c->scheduled_at?->format('Y-m-d'),
                'publishedAt' => $c->published_at?->format('Y-m-d'),
                'status' => $c->status, 'statusLabel' => $st($c->status), 'statusTone' => $tone($c->status),
                'needsAction' => in_array($c->status, ['agency_review', 'client_review', 'changes_requested'], true),
            ])->values(),
            // مراحل المحتوى — لشريط سير العمل المرئي
            'contentStages' => collect(['draft' => 'مسودة', 'agency_review' => 'مراجعة الوكالة', 'client_review' => 'مراجعة العميل', 'changes_requested' => 'تعديلات مطلوبة', 'approved' => 'معتمد', 'scheduled' => 'مجدول', 'published' => 'منشور'])
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => $content->where('status', $key)->count()])
                ->values(),
            'contracts' => $contracts->map(function ($c) use ($st, $tone) {
                $expiring = $c->end_date && $c->end_date->isFuture() && $c->end_date->diffInDays(now()) <= 30;
                return [
                    'id' => $c->id, 'title' => $c->title, 'number' => $c->contract_number, 'party' => $c->creator?->display_name,
                    'valueMinor' => (int) $c->value_minor,
                    'sentAt' => $c->sent_at?->format('Y-m-d'), 'signedAt' => $c->signed_at?->format('Y-m-d'),
                    'signedBy' => $c->signed_by_name,
                    'startDate' => $c->start_date?->format('Y-m-d'), 'endDate' => $c->end_date?->format('Y-m-d'),
                    'expiringSoon' => $expiring, 'expired' => (bool) ($c->end_date && $c->end_date->isPast()),
                    'awaitingSignature' => $c->status === 'sent',
                    'status' => $c->status, 'statusLabel' => $st($c->status), 'statusTone' => $tone($c->status),
                ];
            })->values(),
            'payouts' => $payouts->take(30)->map(fn ($p) => [
                'id' => $p->id, 'number' => $p->payout_number, 'creator' => $p->creator?->display_name,
                'amountMinor' => (int) $p->amount_minor,
                'campaign' => $campaignNames[$p->campaign_id] ?? null,
                'dueDate' => $p->due_date?->format('Y-m-d'), 'paidAt' => $p->paid_at?->format('Y-m-d'),
                'overdue' => (bool) ($p->due_date && $p->due_date->isPast() && ! in_array($p->status, ['paid', 'cancelled'], true)),
                'status' => $p->status, 'statusLabel' => $st($p->status), 'statusTone' => $tone($p->status),
            ])->values(),
            // Financial workspace: توزيع حسب الحملة + جدول زمني + تنبيهات
            'finance' => [
                'byCampaign' => $campaigns->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name,
                    'budgetMinor' => (int) $c->budget_minor,
                    'costMinor' => (int) (($collabsByCampaign[$c->id] ?? collect())->sum('fee_minor')),
                    'payoutsPaid' => (int) (($payoutsByCampaign[$c->id] ?? collect())->where('status', 'paid')->sum('amount_minor')),
                ])->filter(fn ($r) => $r['budgetMinor'] > 0 || $r['costMinor'] > 0)->values(),
                'buckets' => [
                    'pending' => (int) $payouts->whereIn('status', ['pending', 'approved', 'scheduled'])->sum('amount_minor'),
                    'paid' => (int) $payouts->where('status', 'paid')->sum('amount_minor'),
                    'overdue' => (int) $payouts->filter(fn ($p) => $p->due_date && $p->due_date->isPast() && ! in_array($p->status, ['paid', 'cancelled'], true))->sum('amount_minor'),
                ],
                'timeline' => $payouts->filter(fn ($p) => $p->paid_at)->sortByDesc('paid_at')->take(8)
                    ->map(fn ($p) => ['at' => $p->paid_at->format('Y-m-d'), 'label' => $p->creator?->display_name ?? $p->payout_number, 'amountMinor' => (int) $p->amount_minor])
                    ->values(),
            ],
            'nextAction' => $nextAction,
            'activity' => $activity,
            // Triage queue: أولوية + SLA + مسؤول + سبب التعطل + آخر تحديث
            'requests' => $requests->take(30)->map(function ($q) use ($st, $tone, $open, $userNames) {
                $isOpen = in_array($q->status, $open, true);
                $breached = (bool) ($q->sla_breached_at && $isOpen);
                $dueSoon = ! $breached && $isOpen && $q->due_at && $q->due_at->isFuture() && $q->due_at->diffInHours(now()) <= 24;
                return [
                    'id' => $q->id, 'title' => $q->title, 'number' => $q->request_number,
                    'type' => $q->type, 'priority' => $q->priority,
                    'priorityLabel' => ['low' => 'منخفضة', 'normal' => 'عادية', 'high' => 'عالية', 'urgent' => 'عاجلة'][$q->priority] ?? $q->priority,
                    'assignee' => $q->assigned_to ? ($userNames[$q->assigned_to] ?? '—') : null,
                    'dueAt' => $q->due_at?->format('Y-m-d H:i'),
                    'updatedAt' => $q->updated_at?->format('Y-m-d'),
                    'status' => $q->status, 'statusLabel' => $st($q->status), 'statusTone' => $tone($q->status),
                    'open' => $isOpen, 'overdue' => $breached, 'dueSoon' => $dueSoon,
                    // سبب التعطل الفعلي
                    'blocked' => $breached ? 'تجاوز مهلة SLA' : ($q->status === 'needs_info' ? 'بانتظار معلومة' : (! $q->assigned_to && $isOpen ? 'غير مُسنَد' : null)),
                    'bucket' => $breached ? 'overdue' : ($q->status === 'submitted' ? 'new' : ($isOpen ? 'open' : 'done')),
                ];
            })->values(),
            'creators' => $creators,
            'documents' => $documents,
            'customFields' => $customFields,
        ]);
    }
}
