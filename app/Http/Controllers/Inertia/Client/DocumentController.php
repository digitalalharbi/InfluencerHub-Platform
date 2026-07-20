<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\CRM\Models\ClientDocument;
use App\Domain\CRM\Services\ClientDocumentService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Domain\CRM\Support\ClientPortalAbilities;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مستندات العميل (React/Inertia) — عرض المرئي للعميل فقط (agency_internal لا يظهر) + تنزيل.
 * معزول على العميل النشِط.
 */
class DocumentController extends Controller
{
    private const CATEGORY_LABEL = [
        'contract' => 'عقد', 'invoice' => 'فاتورة', 'report' => 'تقرير', 'brief' => 'بريف',
        'legal' => 'قانوني', 'other' => 'أخرى',
    ];

    public function index(Request $r): Response
    {
        $c = $r->attributes->get('activeClient');
        $docs = ClientDocument::where('client_id', $c->id)->where('visibility', 'client_visible')->latest()->get()
            ->map(fn (ClientDocument $d) => [
                'id' => $d->id, 'title' => $d->title, 'category' => $d->category,
                'categoryLabel' => self::CATEGORY_LABEL[$d->category] ?? $d->category,
                'name' => $d->original_name, 'ext' => $d->extension,
                'sizeKb' => $d->size_bytes ? (int) round($d->size_bytes / 1024) : null,
                'uploadedAt' => $d->created_at?->format('Y-m-d'),
            ]);

        return Inertia::render('ClientPortal/Documents/Index', [
            'clientName' => $c->display_name,
            'docs' => $docs,
        ]);
    }

    public function download(Request $r, int $document, ClientDocumentService $svc)
    {
        $c = $r->attributes->get('activeClient');
        $doc = ClientDocument::where('id', $document)->where('client_id', $c->id)->first();
        abort_unless($doc, 404);
        return $svc->download($doc, $r->user()->id, 'client', $r->query('preview') ? 'preview' : 'download');
    }

    /**
     * رفع مستند من العميل — يصل بحالة «بانتظار مراجعة الوكالة» لا معتمدًا.
     * البوابة MANAGE_DOCS لا مجرّد عضوية.
     */
    public function upload(Request $r, ClientDocumentService $svc)
    {
        $c = $r->attributes->get('activeClient');
        abort_unless(ClientPortalAbilities::can($r->attributes->get('clientMembership')->role, ClientPortalAbilities::MANAGE_DOCS), 403);
        $r->validate([
            'category' => 'required|string',
            'title' => 'required|string|max:200',
            'file' => 'required|file|max:20480',
            'replace_id' => 'nullable|integer',
        ]);

        try {
            $svc->upload($c, $r->input('category'), $r->input('title'), $r->file('file'), $r->user()->id, 'client_visible', $r->input('replace_id'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('ok', 'تم رفع المستند (بانتظار مراجعة الوكالة).');
    }
}
