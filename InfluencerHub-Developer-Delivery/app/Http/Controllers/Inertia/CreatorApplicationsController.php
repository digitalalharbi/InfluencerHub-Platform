<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Creators\Actions\ApproveCreatorApplication;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Creators\Models\{CreatorApplication, CreatorApplicationDocument, CreatorApplicationMessage, CreatorApplicationReview};
use App\Domain\Creators\Services\ApplicationDocumentService;
use App\Domain\Creators\Services\CreatorApplicationService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * طلبات انضمام المبدعين — مراجعة الوكالة (React/Inertia). طابور فرز + تفاصيل + إجراءات
 * (إسناد/طلب استكمال/رفض/قبول) تعيد استخدام CreatorApplicationService + ApproveCreatorApplication.
 * Policy(viewAny/review/approve CreatorApplication)، معزولة بالمستأجر.
 */
class CreatorApplicationsController extends Controller
{
    public function __construct(private CreatorApplicationService $svc) {}

    private const TYPE_LABEL = ['influencer' => 'مؤثر', 'ugc_creator' => 'صانع UGC', 'both' => 'كلاهما'];

    public function index(Request $r): Response
    {
        $this->authorize('viewAny', CreatorApplication::class);
        $q = CreatorApplication::query()->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('full_name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%")->orWhere('reference', 'ilike', "%{$s}%"));
        }
        if ($s = $r->query('status')) $q->where('status', $s);
        if ($s = $r->query('type')) $q->where('account_type', $s);

        $applications = $q->paginate(15)->withQueryString()->through(fn (CreatorApplication $a) => $this->row($a));

        return Inertia::render('CreatorApplications/Index', [
            'applications' => $applications,
            'filters' => $r->only('q', 'status', 'type'),
            'summary' => [
                'pending' => CreatorApplication::whereIn('status', ['submitted', 'under_review', 'completion_required'])->count(),
                'total' => CreatorApplication::count(),
            ],
        ]);
    }

    public function show(Request $r, CreatorApplication $application): Response
    {
        $this->authorize('view', $application);
        $application->load('platforms', 'statusHistory', 'documents', 'messages', 'reviews');
        $actorIds = $application->statusHistory->pluck('actor_id')
            ->merge($application->reviews->pluck('reviewer_id'))->filter()->unique();
        $actors = User::whereIn('id', $actorIds)->pluck('name', 'id');
        $history = $application->statusHistory->sortByDesc('occurred_at')->take(15)->map(fn ($h) => [
            'to' => __("statuses.{$h->to_status}"), 'tone' => __("statuses.tone.{$h->to_status}"),
            'actor' => $actors[$h->actor_id] ?? 'النظام', 'note' => $h->reason ?? null,
            'at' => $h->occurred_at?->format('Y-m-d H:i'),
        ])->values();

        return Inertia::render('CreatorApplications/Show', [
            'application' => $this->row($application) + [
                'phone' => $application->phone, 'city' => $application->city, 'bio' => $application->bio,
                'categories' => $application->categories ?? [],
                'platforms' => $application->platforms->map(fn ($p) => [
                    'platform' => \App\Support\Platforms\PlatformRegistry::label($p->platform),
                    'username' => $p->username, 'followers' => (int) $p->followers_count,
                    'status' => __("statuses.{$p->status}"), 'statusTone' => __("statuses.tone.{$p->status}"),
                ])->values(),
                'reviewer' => $application->assigned_reviewer_id ? (User::find($application->assigned_reviewer_id)?->name) : null,
                'rejectionReason' => $application->rejection_reason,
            ],
            'history' => $history,
            'documents' => $application->documents->map(fn (CreatorApplicationDocument $d) => [
                'id' => $d->id, 'title' => $d->original_name, 'kind' => $d->kind,
                'sizeKb' => (int) round(($d->size_bytes ?? 0) / 1024),
                'status' => $d->status,
                'uploadedAt' => $d->created_at?->format('Y-m-d'),
            ])->values(),
            'messages' => $application->messages->sortByDesc('id')->take(30)->values()->map(fn (CreatorApplicationMessage $m) => [
                'body' => $m->body, 'fromAgency' => $m->sender_type === 'agency',
                'at' => $m->created_at?->format('Y-m-d H:i'),
            ]),
            // ملاحظات داخلية — لا تصل مقدّم الطلب
            'notes' => $application->reviews->where('decision', 'note')->sortByDesc('id')->take(20)->values()
                ->map(fn (CreatorApplicationReview $rv) => [
                    'body' => $rv->notes, 'by' => $actors[$rv->reviewer_id] ?? '—',
                    'at' => $rv->created_at?->format('Y-m-d H:i'),
                ]),
            'verification' => [
                'mowthooq' => $application->mowthooq_status,
                'mowthooqReason' => $application->mowthooq_rejection_reason,
                'financial' => $application->financial_verification_status,
            ],
            'canReview' => $r->user()->can('review', $application),
            'canApprove' => $r->user()->can('approve', $application),
            'isPending' => in_array($application->status, ['submitted', 'under_review', 'completion_required'], true),
        ]);
    }

    public function assign(Request $r, CreatorApplication $application)
    {
        $this->authorize('review', $application);
        $this->svc->transition($application, $application->status === 'submitted' ? 'under_review' : $application->status,
            $r->user()->id, ['assigned_reviewer_id' => $r->user()->id, 'reason' => 'إسناد المراجعة']);
        return back()->with('ok', 'أُسنِدت المراجعة إليك.');
    }

    public function requestCompletion(Request $r, CreatorApplication $application)
    {
        $this->authorize('review', $application);
        $data = $r->validate(['message' => 'required|string|max:1000']);
        $this->svc->transition($application, 'completion_required', $r->user()->id, ['reason' => 'مطلوب استكمال', 'applicant_message' => $data['message']]);
        return back()->with('ok', 'طُلب استكمال البيانات من المتقدّم.');
    }

    public function reject(Request $r, CreatorApplication $application)
    {
        $this->authorize('review', $application);
        $data = $r->validate(['reason' => 'required|string|max:500']);
        $this->svc->transition($application, 'rejected', $r->user()->id, ['reason' => $data['reason'], 'rejection_reason' => $data['reason'], 'reviewed_at' => now()]);
        return back()->with('ok', 'رُفض الطلب.');
    }

    public function approve(Request $r, CreatorApplication $application, ApproveCreatorApplication $action)
    {
        $this->authorize('approve', $application);
        try {
            $creator = $action->handle($this->org(), $application, $r->user());
        } catch (\App\Domain\Billing\Exceptions\EntitlementLimitExceeded) {
            return back()->withErrors(['approve' => 'تم بلوغ حد المبدعين (creators.max) في خطتك.']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        }
        return redirect(MountPrefix::path($r, "/creators/{$creator->id}"))->with('ok', 'تم القبول وإنشاء حساب المبدع.');
    }

    private function org(): ?Organization
    {
        return TenantContext::organizationId() ? Organization::find(TenantContext::organizationId()) : null;
    }

    private function row(CreatorApplication $a): array
    {
        return [
            'id' => $a->id, 'reference' => $a->reference, 'name' => $a->full_name, 'email' => $a->email,
            'type' => $a->account_type, 'typeLabel' => self::TYPE_LABEL[$a->account_type] ?? $a->account_type,
            // النص أعلاه يسقط القدرات إلى ثلاث قيم؛ هذه هي القائمة التي اختارها المتقدّم
            'capabilities' => array_map(
                fn (string $k) => \App\Domain\Creators\Models\CreatorCapability::label($k),
                \App\Domain\Creators\Services\CreatorCapabilityService::normalize($a->capabilities ?? []),
            ),
            'country' => $a->country_code, 'status' => $a->status,
            'statusLabel' => __("statuses.{$a->status}"), 'statusTone' => __("statuses.tone.{$a->status}"),
            'submittedAt' => $a->submitted_at?->format('Y-m-d'),
        ];
    }

    /* ===== إجراءات نُقلت من نسخة Blade كما هي ===== */

    public function suspend(Request $r, CreatorApplication $application)
    {
        $this->authorize('review', $application);
        $this->svc->transition($application, 'suspended', $r->user()->id, ['reason' => $r->input('reason', 'تعليق إداري')]);

        return back()->with('ok', 'عُلّق الطلب.');
    }

    /** ملاحظة داخلية — لا تصل مقدّم الطلب. */
    public function addNote(Request $r, CreatorApplication $application)
    {
        $this->authorize('review', $application);
        $data = $r->validate(['notes' => 'required|string|max:2000']);
        TenantContext::withTenant($application->tenant_id, function () use ($application, $r, $data) {
            CreatorApplicationReview::create([
                'tenant_id' => $application->tenant_id, 'application_id' => $application->id,
                'reviewer_id' => $r->user()->id, 'decision' => 'note', 'notes' => $data['notes'], 'created_at' => now(),
            ]);
            AuditLogger::log('creator_application.note', $application, [], $application->tenant_id, $r->user()->id);
        }, TenantContext::organizationId());

        return back()->with('ok', 'أُضيفت الملاحظة الداخلية.');
    }

    public function sendMessage(Request $r, CreatorApplication $application)
    {
        $this->authorize('review', $application);
        $data = $r->validate(['body' => 'required|string|max:2000']);
        TenantContext::withTenant($application->tenant_id, function () use ($application, $r, $data) {
            CreatorApplicationMessage::create([
                'tenant_id' => $application->tenant_id, 'application_id' => $application->id,
                'sender_type' => 'agency', 'sender_id' => $r->user()->id, 'body' => $data['body'], 'created_at' => now(),
            ]);
        }, TenantContext::organizationId());

        return back()->with('ok', 'أُرسلت الرسالة.');
    }

    public function reviewMowthooq(Request $r, CreatorApplication $application)
    {
        $this->authorize('review', $application);
        $data = $r->validate(['decision' => 'required|in:verified,rejected', 'reason' => 'nullable|string|max:500']);
        TenantContext::withTenant($application->tenant_id, function () use ($application, $r, $data) {
            $application->update([
                'mowthooq_status' => $data['decision'],
                'mowthooq_rejection_reason' => $data['decision'] === 'rejected' ? ($data['reason'] ?? null) : null,
            ]);
            AuditLogger::log('creator_application.mowthooq_' . $data['decision'], $application, [], $application->tenant_id, $r->user()->id);
        }, TenantContext::organizationId());

        return back()->with('ok', 'حُدِّثت حالة موثوق.');
    }

    public function reviewFinancial(Request $r, CreatorApplication $application)
    {
        $this->authorize('review', $application);
        $data = $r->validate(['decision' => 'required|in:verified,rejected']);
        TenantContext::withTenant($application->tenant_id, function () use ($application, $r, $data) {
            $application->update(['financial_verification_status' => $data['decision']]);
            AuditLogger::log('creator_application.financial_' . $data['decision'], $application, [], $application->tenant_id, $r->user()->id);
        }, TenantContext::organizationId());

        return back()->with('ok', 'حُدِّثت حالة التحقق المالي.');
    }

    /** تنزيل/معاينة مستند طلب — استجابة ملف لا صفحة Inertia. */
    public function downloadDocument(Request $r, CreatorApplication $application, CreatorApplicationDocument $document, ApplicationDocumentService $docs)
    {
        $this->authorize('view', $application);
        // منع IDOR: المستند يجب أن يخصّ هذا الطلب
        abort_unless($document->application_id === $application->id, 404);

        return $docs->download($document, $r->user()->id, $r->query('preview') ? 'preview' : 'download');
    }
}
