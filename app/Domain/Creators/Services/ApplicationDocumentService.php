<?php
namespace App\Domain\Creators\Services;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Creators\Models\{CreatorApplication, CreatorApplicationDocument, CreatorApplicationDocumentVersion, CreatorApplicationDocumentAccessLog};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** رفع/تنزيل/حذف ملفات الطلب: قرص خاص، MIME allowlist، منع تنفيذي، checksum، تدقيق. */
class ApplicationDocumentService {
    public function rules(string $category): array {
        $r = config("creators.uploads.$category");
        if (! $r) throw new RuntimeException("فئة ملف غير معرّفة: $category");
        return $r;
    }

    /** يرفع ملفًا لطلب (مسار معزول tenant/application، اسم مولّد). يعيد المستند. */
    public function upload(CreatorApplication $app, string $category, UploadedFile $file, ?int $uploaderId = null): CreatorApplicationDocument {
        $rules = $this->rules($category);
        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());

        // طبقات التحقق: MIME allowlist + extension + منع تنفيذي + الحجم
        if (! in_array($mime, $rules['mimes'], true)) throw new RuntimeException("نوع ملف غير مسموح: $mime");
        if (! in_array($ext, $rules['ext'], true)) throw new RuntimeException("امتداد غير مسموح: .$ext");
        if (in_array($ext, config('creators.blocked_ext', []), true)) throw new RuntimeException('ملف تنفيذي ممنوع.');
        if ($file->getSize() > $rules['max']) throw new RuntimeException('حجم الملف يتجاوز الحد المسموح.');

        $checksum = hash_file('sha256', $file->getRealPath());
        // مسار خاص معزول: applications/{tenant}/{application}/{category}/{uuid.ext}
        $dir = "applications/{$app->tenant_id}/{$app->id}/{$category}";
        $stored = Str::uuid()->toString() . '.' . $ext;
        $path = $file->storeAs($dir, $stored, 'local'); // storage/app/private (لا رابط عام)

        return TenantContext::withTenant($app->tenant_id, function () use ($app, $category, $path, $stored, $file, $mime, $ext, $checksum, $uploaderId) {
        // استبدال ملف الفئة نفسها للفئات الأحادية (avatar/iban/mowthooq/identity): نُبقي نسخة سابقة
        $existing = in_array($category, ['avatar', 'iban_document', 'mowthooq_document', 'identity_document'], true)
            ? CreatorApplicationDocument::where('application_id', $app->id)->where('kind', $category)->first() : null;

        if ($existing) {
            $ver = $existing->versions()->max('version') + 1;
            CreatorApplicationDocumentVersion::create(['tenant_id' => $app->tenant_id, 'document_id' => $existing->id,
                'version' => $ver, 'path' => $existing->path, 'checksum_sha256' => $existing->checksum_sha256,
                'size_bytes' => $existing->size_bytes, 'uploaded_by' => $existing->uploaded_by, 'created_at' => now()]);
            $existing->update(['path' => $path, 'original_name' => $file->getClientOriginalName(), 'stored_name' => $stored,
                'mime' => $mime, 'extension' => $ext, 'size_bytes' => $file->getSize(), 'checksum_sha256' => $checksum, 'uploaded_by' => $uploaderId]);
            $doc = $existing;
        } else {
            $doc = CreatorApplicationDocument::create(['tenant_id' => $app->tenant_id, 'application_id' => $app->id,
                'kind' => $category, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(),
                'stored_name' => $stored, 'mime' => $mime, 'extension' => $ext, 'size_bytes' => $file->getSize(),
                'checksum_sha256' => $checksum, 'uploaded_by' => $uploaderId, 'status' => 'uploaded']);
        }
        AuditLogger::log('application_document.uploaded', $doc, ['category' => $category, 'size' => $file->getSize(), 'mime' => $mime], $app->tenant_id, $uploaderId);
        return $doc;
        });
    }

    /** تنزيل مُصرَّح: يسجّل الوصول، يبثّ من القرص الخاص، لا يكشف المسار. */
    public function download(CreatorApplicationDocument $doc, ?int $userId, string $action = 'download') {
        TenantContext::withTenant($doc->tenant_id, function () use ($action, $doc, $userId) {
            abort_unless(Storage::disk($doc->disk)->exists($doc->path), 404);
            CreatorApplicationDocumentAccessLog::create(['tenant_id' => $doc->tenant_id, 'document_id' => $doc->id,
                'user_id' => $userId, 'action' => $action, 'ip' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now()]);
            AuditLogger::log('application_document.downloaded', $doc, ['action' => $action], $doc->tenant_id, $userId);
        });
        return Storage::disk($doc->disk)->download($doc->path, $doc->original_name);
    }

    public function delete(CreatorApplicationDocument $doc, ?int $userId): void {
        TenantContext::withTenant($doc->tenant_id, function () use ($doc, $userId) {
            AuditLogger::log('application_document.deleted', $doc, [], $doc->tenant_id, $userId);
            $doc->delete(); // حذف ناعم — الملف الفعلي يبقى للتدقيق حتى تنظيف مجدول
        });
    }

    /** إجمالي حجم ملفات مستأجر (لاستهلاك التخزين). */
    public function tenantStorageBytes(int $tenantId): int {
        TenantContext::withBypass(function () use ($tenantId) {
            $b = (int) CreatorApplicationDocument::where('tenant_id', $tenantId)->sum('size_bytes');
        });
        return $b;
    }
}
