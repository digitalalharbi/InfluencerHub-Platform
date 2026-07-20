<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\{Client, ClientDocument};
use App\Domain\Identity\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
class UploadClientDocument {
    /** أنواع MIME المسموح بها فقط (allowlist لا blocklist). */
    public const ALLOWED_MIME = [
        'application/pdf','image/png','image/jpeg','image/webp',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    public const MAX_BYTES = 20 * 1024 * 1024; // 20MB

    public function handle(Client $client, UploadedFile $file, string $category, string $title, User $actor): ClientDocument {
        if (! in_array($category, ClientDocument::CATEGORIES, true)) { throw new RuntimeException('فئة مستند غير صالحة.'); }
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIME, true)) { throw new RuntimeException("نوع ملف غير مسموح: {$mime}"); }
        if ($file->getSize() > self::MAX_BYTES) { throw new RuntimeException('الملف يتجاوز الحد المسموح (20MB).'); }

        $checksum = hash_file('sha256', $file->getRealPath());
        // مسار خاص مقسّم بالمستأجر/العميل، اسم عشوائي (لا يكشف الاسم الأصلي، لا تخمين IDOR)
        $dir = "clients/{$client->tenant_id}/{$client->id}";
        $stored = $file->storeAs($dir, Str::uuid() . '.' . $file->getClientOriginalExtension(), 'local');

        $doc = ClientDocument::create([
            'tenant_id' => $client->tenant_id, 'client_id' => $client->id, 'category' => $category,
            'title' => $title, 'disk' => 'local', 'path' => $stored, 'original_name' => $file->getClientOriginalName(),
            'mime' => $mime, 'size_bytes' => $file->getSize(), 'checksum_sha256' => $checksum, 'uploaded_by' => $actor->id,
        ]);
        AuditLogger::log('client_document.uploaded', $doc, ['category' => $category, 'size' => $file->getSize(), 'mime' => $mime], $client->tenant_id, $actor->id);
        return $doc;
    }
}
