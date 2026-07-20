<?php
namespace App\Domain\CRM\Services;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\{Client, ClientDocument, ClientDocumentVersion, ClientDocumentAccessLog, ClientDocumentReview};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** مستندات العميل الخاصّة: رفع مع Versioning، تنزيل محكوم بالرؤية، مراجعة الوكالة. */
class ClientDocumentService {
    public const ALLOWED_MIME = ['application/pdf','image/png','image/jpeg','image/webp',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    public const ALLOWED_EXT = ['pdf','png','jpg','jpeg','webp','doc','docx'];
    public const BLOCKED_EXT = ['php','phtml','exe','sh','bat','js','jar','com','msi','dll','app','py','rb','htaccess'];
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** رفع/استبدال بنسخة جديدة (لا استبدال صامت). العميل يرفع client_visible فقط. */
    public function upload(Client $client, string $category, string $title, UploadedFile $file, ?int $uploaderId, string $visibility = 'client_visible', ?int $replaceDocId = null): ClientDocument {
        if (! in_array($category, ClientDocument::CATEGORIES, true)) throw new RuntimeException('فئة مستند غير صالحة.');
        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($mime, self::ALLOWED_MIME, true)) throw new RuntimeException("نوع ملف غير مسموح: {$mime}");
        if (! in_array($ext, self::ALLOWED_EXT, true) || in_array($ext, self::BLOCKED_EXT, true)) throw new RuntimeException("امتداد غير مسموح: .{$ext}");
        if ($file->getSize() > self::MAX_BYTES) throw new RuntimeException('الملف يتجاوز 20MB.');

        $checksum = hash_file('sha256', $file->getRealPath());
        $dir = "clients/{$client->tenant_id}/{$client->id}/documents/{$category}";
        $stored = Str::uuid() . '.' . $ext;
        $path = $file->storeAs($dir, $stored, 'local');

        return TenantContext::withTenant($client->tenant_id, function () use ($client, $replaceDocId, $path, $stored, $file, $mime, $ext, $checksum, $uploaderId, $category, $visibility, $title) {
        $existing = $replaceDocId ? ClientDocument::where('id', $replaceDocId)->where('client_id', $client->id)->first() : null;
        if ($existing) {
            // Versioning: نحفظ النسخة الحالية ثم نحدّث لإصدار جديد → إعادة المراجعة
            ClientDocumentVersion::create(['tenant_id' => $client->tenant_id, 'document_id' => $existing->id, 'version_number' => $existing->version_number,
                'path' => $existing->path, 'checksum_sha256' => $existing->checksum_sha256, 'size_bytes' => $existing->size_bytes, 'uploaded_by' => $existing->uploaded_by, 'created_at' => now()]);
            $existing->update(['path' => $path, 'original_name' => $file->getClientOriginalName(), 'stored_name' => $stored, 'mime' => $mime,
                'extension' => $ext, 'size_bytes' => $file->getSize(), 'checksum_sha256' => $checksum, 'version_number' => $existing->version_number + 1,
                'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'rejection_reason' => null, 'uploaded_by' => $uploaderId]);
            $doc = $existing;
        } else {
            $doc = ClientDocument::create(['tenant_id' => $client->tenant_id, 'client_id' => $client->id, 'category' => $category,
                'visibility' => $visibility, 'title' => $title, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(),
                'stored_name' => $stored, 'mime' => $mime, 'extension' => $ext, 'size_bytes' => $file->getSize(), 'checksum_sha256' => $checksum,
                'version_number' => 1, 'status' => 'pending', 'uploaded_by' => $uploaderId]);
        }
        AuditLogger::log('client_document.uploaded', $doc, ['category' => $category, 'version' => $doc->version_number], $client->tenant_id, $uploaderId);
            return $doc;
        });
    }

    /** تنزيل محكوم: agency_internal لا يُتاح للعميل مطلقًا. يسجّل الوصول. */
    public function download(ClientDocument $doc, ?int $userId, string $actorType, string $action = 'download') {
        if ($actorType === 'client' && $doc->visibility === 'agency_internal') abort(403); // لا يُعرض للعميل
        TenantContext::withTenant($doc->tenant_id, function () use ($action, $actorType, $doc, $userId) {
            abort_unless(Storage::disk($doc->disk)->exists($doc->path), 404);
            ClientDocumentAccessLog::create(['tenant_id' => $doc->tenant_id, 'document_id' => $doc->id, 'user_id' => $userId,
                'actor_type' => $actorType, 'action' => $action, 'ip' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now()]);
            AuditLogger::log('client_document.downloaded', $doc, ['actor' => $actorType], $doc->tenant_id, $userId);
        });
        return Storage::disk($doc->disk)->download($doc->path, $doc->original_name);
    }

    /** مراجعة الوكالة: approved|changes_requested|rejected. */
    public function review(ClientDocument $doc, int $reviewerId, string $decision, ?string $note = null): void {
        TenantContext::withTenant($doc->tenant_id, function () use ($decision, $doc, $note, $reviewerId) {
            $doc->update(['status' => $decision, 'reviewed_by' => $reviewerId, 'reviewed_at' => now(), 'rejection_reason' => in_array($decision, ['rejected','changes_requested']) ? $note : null]);
            ClientDocumentReview::create(['tenant_id' => $doc->tenant_id, 'document_id' => $doc->id, 'reviewer_id' => $reviewerId, 'decision' => $decision, 'note' => $note, 'created_at' => now()]);
            AuditLogger::log("client_document.$decision", $doc, [], $doc->tenant_id, $reviewerId);
        });
    }
}
