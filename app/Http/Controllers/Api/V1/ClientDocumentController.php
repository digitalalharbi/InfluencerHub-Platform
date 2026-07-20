<?php
namespace App\Http\Controllers\Api\V1;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Actions\{UploadClientDocument, DeleteClientDocument};
use App\Domain\CRM\Models\{Client, ClientDocument};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مستندات العميل على قرص خاص (storage/app/private). لا روابط عامة دائمة.
 * العزل: {client} و{document} مربوطان عبر Route-Model-Binding المحكوم بـTenantScope
 * (fail-closed) → مستأجر آخر يحصل 404. إضافة: نتحقق أن المستند يخص العميل المطلوب.
 */
class ClientDocumentController extends Controller {
    public function index(Client $client) {
        $this->authorize('view', $client);
        return response()->json(['data' => $client->documents()->latest()->get()]);
    }

    public function store(Request $r, Client $client, UploadClientDocument $action) {
        $this->authorize('manageDocuments', $client);
        $r->validate([
            'file' => 'required|file|max:20480',
            'category' => 'required|string',
            'title' => 'required|string|max:200',
        ]);
        $doc = $action->handle($client, $r->file('file'), $r->input('category'), $r->input('title'), $r->user('sanctum'));
        return response()->json(['data' => $doc], 201);
    }

    public function download(Request $r, Client $client, ClientDocument $document): StreamedResponse {
        $this->authorize('view', $client);
        // منع IDOR عبر تبديل المعرفات: المستند يجب أن يخص هذا العميل
        abort_unless($document->client_id === $client->id, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        AuditLogger::log('client_document.downloaded', $document, [], $document->tenant_id, $r->user('sanctum')?->id);
        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Request $r, Client $client, ClientDocument $document, DeleteClientDocument $action) {
        $this->authorize('manageDocuments', $client);
        abort_unless($document->client_id === $client->id, 404);
        $action->handle($document, $r->user('sanctum'));
        return response()->json(['message' => 'تم الحذف']);
    }
}
