<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Models\{Client, ClientDocument, ClientProfileChangeRequest};
use App\Domain\CRM\Services\{ClientDocumentService, ClientProfileService};
use App\Domain\CRM\Support\ClientNotifier;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مراجعات العملاء (React/Inertia) — الوكالة تراجع طلبات تعديل الملف القانوني ومستندات العملاء.
 * تعيد استخدام ClientProfileService/ClientDocumentService. Policy(managePortal/manageDocuments)، معزولة.
 */
class ClientReviewsController extends Controller
{
    public function __construct(private ClientNotifier $notifier) {}

    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Client::class);

        $changeRequests = ClientProfileChangeRequest::with('client')->whereIn('status', ['submitted', 'under_review'])
            ->latest()->paginate(15, ['*'], 'cr')->withQueryString()
            ->through(fn (ClientProfileChangeRequest $cr) => [
                'id' => $cr->id, 'client' => $cr->client?->display_name,
                'status' => $cr->status, 'statusLabel' => __("statuses.{$cr->status}"), 'statusTone' => __("statuses.tone.{$cr->status}"),
                'changes' => collect($cr->changes ?? [])->map(fn ($v, $k) => [
                    'field' => $k,
                    'old' => is_array($v) ? (is_scalar($v['old'] ?? null) ? (string) $v['old'] : null) : null,
                    'value' => is_array($v) ? (is_scalar($v['new'] ?? null) ? (string) $v['new'] : json_encode($v['new'] ?? $v, JSON_UNESCAPED_UNICODE)) : (string) $v,
                ])->values(),
                'at' => $cr->created_at?->format('Y-m-d H:i'),
            ]);

        $documents = ClientDocument::with('client')->where('status', 'pending')
            ->latest()->paginate(15, ['*'], 'doc')->withQueryString()
            ->through(fn (ClientDocument $d) => [
                'id' => $d->id, 'title' => $d->title, 'client' => $d->client?->display_name,
                'category' => $d->category, 'name' => $d->original_name,
                'at' => $d->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('ClientReviews/Index', [
            'changeRequests' => $changeRequests,
            'documents' => $documents,
            'tab' => $r->query('tab', 'profile'),
        ]);
    }

    public function approveProfile(Request $r, ClientProfileChangeRequest $changeRequest, ClientProfileService $svc)
    {
        $client = Client::findOrFail($changeRequest->client_id);
        $this->authorize('managePortal', $client);
        if (! in_array($changeRequest->status, ['submitted', 'under_review'], true)) {
            return back()->withErrors(['cr' => 'هذا الطلب لم يعد قابلًا للمراجعة.']);
        }
        $svc->approveChangeRequest($changeRequest, $r->user()->id, 'approved', $r->input('note'));
        $this->notifier->toUser($changeRequest->tenant_id, $changeRequest->requested_by, 'profile.change_approved', 'profile',
            'اعتُمد طلب تعديل بياناتك القانونية', 'طُبّقت البيانات المطلوبة على ملف العميل.', '/client/profile', ['change_request_id' => $changeRequest->id], $changeRequest);
        return back()->with('ok', 'اعتُمد طلب التعديل وطُبّقت البيانات القانونية.');
    }

    public function rejectProfile(Request $r, ClientProfileChangeRequest $changeRequest, ClientProfileService $svc)
    {
        $client = Client::findOrFail($changeRequest->client_id);
        $this->authorize('managePortal', $client);
        $data = $r->validate(['note' => 'required|string|max:500']);
        if (! in_array($changeRequest->status, ['submitted', 'under_review'], true)) {
            return back()->withErrors(['cr' => 'هذا الطلب لم يعد قابلًا للمراجعة.']);
        }
        $svc->approveChangeRequest($changeRequest, $r->user()->id, 'rejected', $data['note']);
        $this->notifier->toUser($changeRequest->tenant_id, $changeRequest->requested_by, 'profile.change_rejected', 'profile',
            'رُفض طلب تعديل بياناتك القانونية', $data['note'], '/client/profile', ['change_request_id' => $changeRequest->id], $changeRequest);
        return back()->with('ok', 'رُفض طلب التعديل ولم تُطبَّق البيانات.');
    }

    public function reviewDocument(Request $r, ClientDocument $document, ClientDocumentService $svc)
    {
        $client = Client::findOrFail($document->client_id);
        $this->authorize('manageDocuments', $client);
        $data = $r->validate(['decision' => 'required|in:approved,changes_requested,rejected', 'note' => 'nullable|string|max:500']);
        if (in_array($data['decision'], ['changes_requested', 'rejected'], true) && blank($data['note'] ?? null)) {
            return back()->withErrors(['doc' => 'سبب التعديل/الرفض مطلوب.']);
        }
        $svc->review($document, $r->user()->id, $data['decision'], $data['note'] ?? null);
        $labels = ['approved' => 'اعتُمد مستندك', 'changes_requested' => 'مطلوب تعديل على مستندك', 'rejected' => 'رُفض مستندك'];
        $this->notifier->toClientMembers($client, "document.{$data['decision']}", 'documents',
            "{$labels[$data['decision']]}: {$document->title}", $data['note'] ?? null, '/client/documents', ['document_id' => $document->id], $document);
        return back()->with('ok', 'سُجّلت مراجعة المستند.');
    }

    /**
     * تنزيل/معاينة مستند عميل — استجابة ملف لا صفحة Inertia،
     * لذا تبقى دالة عادية. نفس بوابة Blade (manageDocuments) وتسجيل الوصول
     * عبر ClientDocumentService (لا رابط عام ثابت للملفات الخاصة).
     */
    public function downloadDocument(Request $r, ClientDocument $document, ClientDocumentService $svc)
    {
        $client = Client::findOrFail($document->client_id); // مُقيَّد بالمستأجر عبر السكوب
        $this->authorize('manageDocuments', $client);

        return $svc->download($document, $r->user()->id, 'agency', $r->query('preview') ? 'preview' : 'download');
    }
}
