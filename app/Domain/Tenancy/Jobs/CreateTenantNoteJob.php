<?php
namespace App\Domain\Tenancy\Jobs;
use App\Domain\Tenancy\Models\Note;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * المهمّة تحمل `tenant_id` صريحًا وتعمل داخل سياقه — النموذج المعتمَد للمهامّ.
 *
 * كانت تستعيد السياق يدويًّا: `reset()` ثم `set($prev)`. وذلك يستعيد **المستأجر
 * وحده** ويُسقط المؤسسة وورشة العمل و`bypass` — استعادة ناقصة تبدو صحيحة.
 * `withTenant` يستعيد اللقطة كاملةً، وحتّى عند الاستثناء.
 */
class CreateTenantNoteJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $tenantId, public string $body) {}
    public function handle(): void {
        TenantContext::withTenant($this->tenantId, fn () => Note::create(['body' => $this->body]));
    }
}
