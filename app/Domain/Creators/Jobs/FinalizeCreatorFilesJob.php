<?php
namespace App\Domain\Creators\Jobs;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Creators\Models\{Creator, CreatorApplication, CreatorApplicationDocument, CreatorPortfolio};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Storage;

/**
 * إتمام نقل ملفات الطلب إلى المبدع بعد Commit — idempotent، يتحقق من checksum،
 * لا يحذف الأصل قبل نجاح النسخ+التحقق. حالات: pending→copying→completed|failed.
 */
class FinalizeCreatorFilesJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public function __construct(public int $applicationId) {}

    public function handle(): void {
        // التجاوز يُفتح لعمل محدَّد ويُغلق بعده حتّى عند الاستثناء. كان يُفتح
        // ويُغلق يدويًّا ستّ مرّات هنا، ولو رمى `link()` بقي مفتوحًا لبقيّة
        // المهمّة — وفي عامل طوابير طويل العمر يمتدّ إلى المهامّ التالية.
        [$creator, $docs] = TenantContext::withBypass(function () {
            $app = CreatorApplication::withoutGlobalScopes()->find($this->applicationId);
            if (! $app || ! $app->creator_id) return [null, collect()];

            return [
                Creator::withoutGlobalScopes()->find($app->creator_id),
                CreatorApplicationDocument::withoutGlobalScopes()->where('application_id', $app->id)
                    ->whereIn('transfer_status', ['pending', 'copying', 'failed'])->get(),
            ];
        });
        if (! $creator) return;

        $disk = Storage::disk('local');
        foreach ($docs as $doc) {
            $dest = "creators/{$creator->tenant_id}/{$creator->id}/{$doc->kind}/" . basename($doc->path);
            $key = "creator-file:{$creator->id}:{$doc->id}";
            TenantContext::withBypass(fn () => $doc->update(['transfer_status' => 'copying', 'transfer_idempotency_key' => $key]));
            try {
                // idempotent: إن كان الوجهة موجودًا بنفس checksum → مكتمل بلا إعادة نسخ
                if (! ($disk->exists($dest) && hash('sha256', $disk->get($dest)) === $doc->checksum_sha256)) {
                    if (! $disk->exists($doc->path)) throw new \RuntimeException('الملف الأصلي مفقود');
                    $disk->copy($doc->path, $dest);
                }
                // تحقّق من النزاهة بعد النسخ
                if (hash('sha256', $disk->get($dest)) !== $doc->checksum_sha256) {
                    throw new \RuntimeException('checksum mismatch بعد النسخ');
                }
                $this->link($creator, $doc, $dest);
                TenantContext::withBypass(fn () => $doc->update(['transfer_status' => 'completed', 'transferred_path' => $dest, 'transferred_at' => now()]));
            } catch (\Throwable $e) {
                TenantContext::withBypass(fn () => $doc->update(['transfer_status' => 'failed'])); // الأصل يبقى؛ إعادة المحاولة آمنة
                AuditLogger::log('creator_file.finalize_failed', $doc, ['error' => $e->getMessage()], $creator->tenant_id);
            }
        }
    }

    private function link(Creator $creator, CreatorApplicationDocument $doc, string $dest): void {
        TenantContext::withBypass(fn () => match ($doc->kind) {
            'avatar' => $creator->update(['avatar_path' => $dest]),
            'mowthooq_document' => $creator->update(['mowthooq_document_path' => $dest]),
            'iban_document' => $creator->update(['iban_document_path' => $dest]),
            'portfolio_image', 'portfolio_video' => CreatorPortfolio::firstOrCreate(
                ['tenant_id' => $creator->tenant_id, 'creator_id' => $creator->id, 'path' => $dest],
                ['type' => str_contains($doc->kind, 'video') ? 'video' : 'image', 'media_type' => $doc->mime, 'status' => 'active']),
            default => null,
        });
    }
}
