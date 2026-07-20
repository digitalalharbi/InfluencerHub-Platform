<?php
namespace App\Console\Commands;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Exceptions\EntitlementLimitExceeded;
use App\Domain\CRM\Actions\{ArchiveClient, CreateClient};
use App\Domain\CRM\Models\{Client, ImportBatch};
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * استيراد عملاء من نظام قديم (CSV أو JSON) مع Mapping قابل للتهيئة.
 * الخيارات: --file --tenant --mapping --dry-run --rollback-batch
 * القواعد: dry-run لا يكتب شيئًا؛ تكرار البريد ضمن المستأجر يُتخطّى؛ يحترم customers.max؛
 * كل استيراد يُنشئ دفعة (import_batch) تتيح التراجع الآمن عن الدفعة كاملةً.
 */
class ImportLegacyClientsCommand extends Command {
    protected $signature = 'import:legacy-clients
        {--file= : مسار ملف CSV أو JSON}
        {--tenant= : مُعرّف المستأجر أو الـslug}
        {--mapping= : مسار ملف JSON للربط أو JSON مضمّن (target=>source)}
        {--dry-run : تحقّق واعرض دون كتابة}
        {--rollback-batch= : معرّف دفعة استيراد للتراجع عنها}';
    protected $description = 'استيراد/تراجع عملاء النظام القديم';

    /** الربط الافتراضي إن لم يُمرَّر --mapping. */
    private const DEFAULT_MAP = [
        'display_name' => 'name', 'legal_name' => 'legal_name', 'email' => 'email',
        'phone' => 'phone', 'sector' => 'sector', 'status' => 'status',
    ];

    public function handle(): int {
        if ($batchId = $this->option('rollback-batch')) {
            return $this->rollback((int) $batchId);
        }

        $tenant = $this->resolveTenant();
        if (! $tenant) { $this->error('مستأجر غير موجود (--tenant).'); return self::FAILURE; }
        $org = Organization::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        if (! $org) { $this->error('لا توجد مؤسسة لهذا المستأجر.'); return self::FAILURE; }

        // الفاعل: أول مدير نشِط في المؤسسة (الاستيراد يُنسب إليه في السجل والتدقيق).
        $actor = \App\Domain\Identity\Models\User::whereHas('memberships', fn ($q) => $q
            ->withoutGlobalScopes()->where('organization_id', $org->id)->where('status', 'active'))->first();
        if (! $actor) { $this->error('لا يوجد مستخدم فاعل في المؤسسة لنسب الاستيراد إليه.'); return self::FAILURE; }

        $file = $this->option('file');
        if (! $file || ! is_file($file)) { $this->error('ملف غير موجود (--file).'); return self::FAILURE; }

        $rows = $this->readRows($file);
        if ($rows === null) { $this->error('صيغة ملف غير مدعومة (CSV/JSON فقط).'); return self::FAILURE; }
        $map = $this->resolveMapping();
        $dry = (bool) $this->option('dry-run');

        return TenantContext::withTenant($tenant->id, function () use ($tenant, $org, $rows, $map, $dry, $file, $actor) {
        $imported = 0; $skipped = 0; $errors = [];

        // نُنشئ الدفعة فقط في وضع الكتابة
        $batch = $dry ? null : ImportBatch::create([
            'tenant_id' => $tenant->id, 'type' => 'legacy_clients', 'source_file' => basename($file),
            'status' => 'completed', 'created_at' => now(),
        ]);

        foreach ($rows as $i => $row) {
            $data = $this->mapRow($row, $map);
            if (empty($data['display_name'])) { $skipped++; $errors[] = "سطر " . ($i + 1) . ": بدون اسم"; continue; }
            // منع التكرار: بريد موجود مسبقًا ضمن المستأجر
            if (! empty($data['email']) && Client::where('email', $data['email'])->exists()) {
                $skipped++; $errors[] = "سطر " . ($i + 1) . ": بريد مكرر ({$data['email']})"; continue;
            }
            if ($dry) { $imported++; continue; }
            try {
                $client = app(CreateClient::class)->handle($org, $data, $actor);
            } catch (EntitlementLimitExceeded) {
                $skipped++; $errors[] = "سطر " . ($i + 1) . ": تجاوز customers.max"; continue;
            } catch (\Throwable $e) {
                $skipped++; $errors[] = "سطر " . ($i + 1) . ": {$e->getMessage()}"; continue;
            }
            $client->forceFill(['import_batch_id' => $batch->id])->saveQuietly();
            $imported++;
        }

        if ($batch) {
            $batch->update(['imported_count' => $imported, 'skipped_count' => $skipped]);
            AuditLogger::log('import.legacy_clients', $batch, ['imported' => $imported, 'skipped' => $skipped], $tenant->id);
        }

        $this->newLine();
        $this->info(($dry ? '[تجربة/DRY-RUN] ' : '') . "المستورَد: {$imported} — المتخطّى: {$skipped}" . ($batch ? " — الدفعة #{$batch->id}" : ''));
        foreach (array_slice($errors, 0, 20) as $e) { $this->line("  • {$e}"); }

        return self::SUCCESS;
        }, $org->id);
    }

    private function rollback(int $batchId): int {
        $batch = ImportBatch::withoutGlobalScopes()->find($batchId);
        if (! $batch) { $this->error("دفعة #{$batchId} غير موجودة."); return self::FAILURE; }
        if ($batch->status === 'rolled_back') { $this->warn('الدفعة متراجَع عنها مسبقًا.'); return self::SUCCESS; }

        $org = Organization::withoutGlobalScopes()->where('tenant_id', $batch->tenant_id)->first();
        return TenantContext::withTenant($batch->tenant_id, function () use ($batch, $org, $batchId) {
        $count = 0;
        DB::transaction(function () use ($batch, $org, &$count) {
            foreach (Client::where('import_batch_id', $batch->id)->get() as $client) {
                app(ArchiveClient::class)->handle($org, $client); // يحرّر الاستهلاك (idempotent)
                $client->delete();                                // حذف ناعم
                $count++;
            }
            $batch->update(['status' => 'rolled_back', 'rolled_back_at' => now()]);
        });
        AuditLogger::log('import.rollback', $batch, ['clients' => $count], $batch->tenant_id);
        $this->info("تم التراجع عن الدفعة #{$batchId}: {$count} عميلًا.");

        return self::SUCCESS;
        }, $org?->id);
    }

    private function resolveTenant(): ?Tenant {
        $t = $this->option('tenant');
        if (! $t) return null;
        return is_numeric($t) ? Tenant::find((int) $t) : Tenant::where('slug', $t)->first();
    }

    private function readRows(string $file): ?array {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            $data = json_decode(file_get_contents($file), true);
            return is_array($data) ? array_values($data) : null;
        }
        if ($ext === 'csv') {
            $rows = []; $h = null;
            if (($fp = fopen($file, 'r')) !== false) {
                while (($line = fgetcsv($fp)) !== false) {
                    if ($h === null) { $h = $line; continue; }
                    $rows[] = array_combine($h, $line);
                }
                fclose($fp);
            }
            return $rows;
        }
        return null;
    }

    private function resolveMapping(): array {
        $m = $this->option('mapping');
        if (! $m) return self::DEFAULT_MAP;
        $json = is_file($m) ? file_get_contents($m) : $m;
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : self::DEFAULT_MAP;
    }

    private function mapRow(array $row, array $map): array {
        $out = [];
        foreach ($map as $target => $source) {
            if (array_key_exists($source, $row) && $row[$source] !== '' && $row[$source] !== null) {
                $out[$target] = $row[$source];
            }
        }
        // حالة افتراضية آمنة
        $valid = ['lead', 'qualified', 'active', 'inactive', 'suspended'];
        if (empty($out['status']) || ! in_array($out['status'], $valid, true)) $out['status'] = 'lead';
        $out['type'] = $out['type'] ?? 'company';
        return $out;
    }
}
