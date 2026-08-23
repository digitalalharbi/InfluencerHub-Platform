<?php

namespace App\Domain\Integrations\Jobs;

use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Services\IntegrationConnectionService;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * وظيفة مزامنة مزوّد — تعمل في الطابور لا في طلب المتصفّح. تعيد المحاولة بتراجع
 * أُسّي محدود؛ الفشل النهائي يُترك في failed_jobs مع الاتّصال بحالة error.
 * تُمرَّر بالمعرّف لا بالنموذج (لا رموز مُسلسَلة في حمولة الطابور).
 */
class SyncProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120]; // ثوانٍ بين المحاولات

    public function __construct(
        public int $connectionId,
        public string $type = 'manual',
        public ?string $capability = null,
    ) {}

    public function handle(IntegrationConnectionService $service): void
    {
        $conn = TenantContext::withBypass(fn () => IntegrationConnection::find($this->connectionId));
        if (! $conn || ! $conn->isConnected()) {
            return; // لا مزامنة لاتّصال غير موصول
        }

        $service->runSync($conn, $this->type, $this->capability, $this->attempts() - 1);
    }
}
