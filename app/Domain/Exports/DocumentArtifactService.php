<?php

namespace App\Domain\Exports;

use App\Domain\Exports\Models\ExportJob;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * أثر مستند موحّد — نسخة ثابتة واحدة لكل (نوع، كيان، صيغة) مبنية على بصمة المصدر
 * (fingerprint). المعاينة والتنزيل يبثّان **نفس** البايتات المخزَّنة، فيتساوى
 * checksum(المعاينة) == checksum(التنزيل) دومًا. تغيّر المصدر يُنتج بصمة جديدة،
 * فتُصبح النسخة القديمة «قديمة» وتُنشأ نسخة محدثة عند الطلب (لا تجديد صامت).
 * خاص بالمستأجر، على قرص خاص، بلا روابط عامّة. [[system-health]] لا علاقة له.
 */
class DocumentArtifactService
{
    private const DISK = 'local';

    public function __construct(private \App\Domain\Exports\Writers\PdfWriter $pdf) {}

    /** بصمة المصدر: لقطة البيانات + إصدار القالب + الصيغة. */
    public function fingerprint(array $sourceData, string $templateVersion, string $format): string
    {
        return hash('sha256', json_encode($sourceData, JSON_UNESCAPED_UNICODE) . '|' . $templateVersion . '|' . $format);
    }

    /** أحدث أثر مخزَّن لهذا الكيان (أيًّا كانت بصمته) — لعرض «توجد نسخة منذ…». */
    public function latest(string $type, Model $subject, string $format = 'pdf'): ?ExportJob
    {
        return ExportJob::where('type', $type)->where('format', $format)
            ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
            ->where('status', 'completed')->latest('id')->first();
    }

    /** هل النسخة الحالية قديمة مقابل بيانات المصدر الآن؟ */
    public function isStale(?ExportJob $artifact, string $currentFingerprint): bool
    {
        return $artifact !== null && $artifact->fingerprint !== $currentFingerprint;
    }

    /**
     * يعيد الأثر الحالي المطابق لبصمة المصدر الآن — يعيد استخدام المخزَّن إن وُجد
     * (فيتطابق للمعاينة والتنزيل)، وإلّا يولّده مرّة واحدة ويخزّنه. idempotent.
     *
     * @param  array  $sourceData  لقطة تحدّد البصمة (تغييرها = نسخة جديدة)
     * @param  callable():string  $renderBytes  يولّد بايتات الملفّ عند الحاجة فقط
     */
    public function current(
        string $type, Model $subject, string $format, string $templateVersion,
        array $sourceData, string $title, callable $renderBytes, ?int $userId = null,
    ): ExportJob {
        $fp = $this->fingerprint($sourceData, $templateVersion, $format);

        $existing = ExportJob::where('type', $type)->where('format', $format)
            ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
            ->where('fingerprint', $fp)->where('status', 'completed')->latest('id')->first();
        if ($existing && $existing->path && Storage::disk($existing->disk ?: self::DISK)->exists($existing->path)) {
            return $existing;
        }

        $bytes = $renderBytes();
        $path = 'artifacts/' . TenantContext::tenantId() . '/' . $type . '/' . uniqid($subject->getKey() . '_', true) . '.' . $format;
        Storage::disk(self::DISK)->put($path, $bytes);

        return ExportJob::create([
            'tenant_id' => TenantContext::tenantId(),
            'user_id' => $userId,
            'type' => $type,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'title' => $title,
            'format' => $format,
            'status' => 'completed',
            'disk' => self::DISK,
            'path' => $path,
            'size_bytes' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'fingerprint' => $fp,
            'template_version' => $templateVersion,
            'completed_at' => now(),
        ]);
    }

    /** يقرأ بايتات الأثر المخزَّنة (نفسها للمعاينة والتنزيل). */
    public function bytes(ExportJob $artifact): string
    {
        return (string) Storage::disk($artifact->disk ?: self::DISK)->get($artifact->path);
    }

    /** مساعد PDF من قالب Blade → بايتات. */
    public function pdfFromView(string $view, array $data): string
    {
        return $this->pdf->fromView($view, $data);
    }
}
