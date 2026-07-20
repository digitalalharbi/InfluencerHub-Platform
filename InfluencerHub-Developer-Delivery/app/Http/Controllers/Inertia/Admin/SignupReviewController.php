<?php

namespace App\Http\Controllers\Inertia\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Onboarding\Models\{SelfSignup, SignupRequest};
use App\Http\Controllers\Controller;
use App\Mail\SignupDecisionMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Mail};
use Inertia\Inertia;
use Inertia\Response;

/**
 * مراجعة طلبات فتح الحساب (مدير النظام).
 *
 * لوحة المدير للقراءة فقط عمومًا، وهذا استثناء مقصود: الطلب بلا قرار ورقة
 * ميّتة. الاستثناء محدود بهذين الفعلين ولا يمتدّ إلى بيانات المستأجرين.
 *
 * الاعتماد لا يُنشئ المستأجر هنا: يفتح مسار تسجيل ذاتيّ مؤكَّد البريد لصاحب
 * الطلب ليضع كلمة مروره بنفسه. هكذا لا نولّد كلمة مرور ولا نرسلها بالبريد،
 * ولا نكرّر منطق التفعيل الموجود في SelfSignupService.
 */
class SignupReviewController extends Controller
{
    private const STATUS_LABEL = [
        'submitted' => 'جديد', 'contacted' => 'جرى التواصل',
        'approved' => 'معتمَد', 'rejected' => 'مرفوض',
    ];

    private const STATUS_TONE = [
        'submitted' => 'info', 'contacted' => 'warning',
        'approved' => 'success', 'rejected' => 'danger',
    ];

    public function index(Request $r): Response
    {
        $status = $r->query('status', 'submitted');
        $type = $r->query('type');

        $q = SignupRequest::query()->latest('id');
        if ($status && $status !== 'all') {
            $q->where('status', $status);
        }
        if (in_array($type, ['client', 'agency'], true)) {
            $q->where('account_type', $type);
        }

        return Inertia::render('Admin/SignupReview', [
            'requests' => $q->paginate(20)->through(fn (SignupRequest $s) => [
                'id' => $s->id,
                'reference' => $s->reference,
                'type' => $s->account_type,
                'typeLabel' => $s->typeLabel(),
                'contactName' => $s->contact_name,
                'email' => $s->email,
                'phone' => $s->phone,
                'company' => $s->company_name,
                'website' => $s->website,
                'teamSize' => $s->team_size,
                'monthlyCampaigns' => $s->monthly_campaigns,
                'notes' => $s->notes,
                'status' => $s->status,
                'statusLabel' => self::STATUS_LABEL[$s->status] ?? $s->status,
                'statusTone' => self::STATUS_TONE[$s->status] ?? 'neutral',
                'reviewNotes' => $s->review_notes,
                'reviewedAt' => $s->reviewed_at?->format('Y-m-d H:i'),
                'createdAt' => $s->created_at?->format('Y-m-d H:i'),
                'isDecided' => in_array($s->status, ['approved', 'rejected'], true),
            ]),
            'filters' => ['status' => $status, 'type' => $type],
            'counts' => [
                'submitted' => SignupRequest::where('status', 'submitted')->count(),
                'approved' => SignupRequest::where('status', 'approved')->count(),
                'rejected' => SignupRequest::where('status', 'rejected')->count(),
            ],
            'statusLabels' => self::STATUS_LABEL,
        ]);
    }

    public function approve(Request $r, SignupRequest $signupRequest): RedirectResponse
    {
        // القرار لا يُتّخذ مرّتين: يمنع اعتمادًا مكرّرًا ينشئ مساحتين
        if ($signupRequest->status === 'approved') {
            return back()->withErrors(['review' => 'هذا الطلب معتمَد بالفعل.']);
        }
        if ($signupRequest->status === 'rejected') {
            return back()->withErrors(['review' => 'هذا الطلب مرفوض. أعِد فتحه قبل الاعتماد.']);
        }

        $note = $r->validate(['review_notes' => 'nullable|string|max:1000'])['review_notes'] ?? null;

        DB::transaction(function () use ($r, $signupRequest, $note) {
            $signupRequest->update([
                'status' => 'approved',
                'review_notes' => $note,
                'reviewed_by' => $r->user()->id,
                'reviewed_at' => now(),
            ]);

            // مسار ذاتي مؤكَّد البريد: صاحب الطلب يضع كلمة مروره بنفسه
            $selfSignup = SelfSignup::create([
                'account_type' => 'agency',
                'email' => $signupRequest->email,
                'status' => 'verified',
                'email_verified_at' => now(),
                'completed_steps' => ['email_verification_pending'],
            ]);

            $this->notifyApproved($signupRequest, $selfSignup);
            $this->audit($r, $signupRequest, 'approved', ['review_notes' => $note]);
        });

        return back()->with('ok', 'اعتُمد الطلب وأُرسل رابط إكمال التسجيل.');
    }

    /**
     * تسجيل أن التواصل جرى قبل القرار.
     *
     * كانت الحالة مُعلنة في الواجهة بلا فعل يبلغها — حالة مستحيلة تُربك من
     * يقرأ الفلاتر. المراجع كثيرًا ما يتواصل قبل الاعتماد، فالحالة مفيدة:
     * تُبلَغ بفعل بدل أن تُحذف.
     */
    public function markContacted(Request $r, SignupRequest $signupRequest): RedirectResponse
    {
        if (in_array($signupRequest->status, ['approved', 'rejected'], true)) {
            return back()->withErrors(['review' => 'صدر القرار في هذا الطلب بالفعل.']);
        }

        $note = $r->validate(['review_notes' => 'nullable|string|max:1000'])['review_notes'] ?? null;

        $signupRequest->update([
            'status' => 'contacted',
            'review_notes' => $note,
            'reviewed_by' => $r->user()->id,
            'reviewed_at' => now(),
        ]);
        $this->audit($r, $signupRequest, 'contacted', ['review_notes' => $note]);

        return back()->with('ok', 'سُجّل أن التواصل جرى.');
    }

    public function reject(Request $r, SignupRequest $signupRequest): RedirectResponse
    {
        if ($signupRequest->status === 'approved') {
            return back()->withErrors(['review' => 'لا يُرفض طلب معتمَد.']);
        }

        // السبب إلزامي: رفض بلا سبب لا يُفيد صاحب الطلب ولا المراجع التالي
        $note = $r->validate(
            ['review_notes' => 'required|string|max:1000'],
            [],
            ['review_notes' => 'سبب الرفض'],
        )['review_notes'];

        DB::transaction(function () use ($r, $signupRequest, $note) {
            $signupRequest->update([
                'status' => 'rejected',
                'review_notes' => $note,
                'reviewed_by' => $r->user()->id,
                'reviewed_at' => now(),
            ]);

            $this->notifyRejected($signupRequest, $note);
            $this->audit($r, $signupRequest, 'rejected', ['review_notes' => $note]);
        });

        return back()->with('ok', 'سُجّل الرفض وأُبلغ مقدّم الطلب.');
    }

    private function notifyApproved(SignupRequest $s, SelfSignup $selfSignup): void
    {
        Mail::to($s->email)->send(new SignupDecisionMail(
            $s, approved: true, setupUrl: url("/register/agency/setup/{$selfSignup->reference}"),
        ));
    }

    private function notifyRejected(SignupRequest $s, string $reason): void
    {
        Mail::to($s->email)->send(new SignupDecisionMail($s, approved: false, reason: $reason));
    }

    /** بلا tenant_id: القرار يسبق المستأجر ويخصّ المنصّة لا مساحة بعينها. */
    private function audit(Request $r, SignupRequest $s, string $action, array $values): void
    {
        AuditLog::create([
            'tenant_id' => null,
            'user_id' => $r->user()->id,
            'actor_name' => $r->user()->name,
            'action' => "signup_request.{$action}",
            'auditable_type' => SignupRequest::class,
            'auditable_id' => $s->id,
            'new_values' => $values,
            'ip' => $r->ip(),
            'user_agent' => substr((string) $r->userAgent(), 0, 500),
            'occurred_at' => now(),
        ]);
    }
}
