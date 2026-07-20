<?php
// أداة اختبار تزامن فقط (ليست أمر artisan/إداري). تُشغَّل كعملية مستقلة من الاختبار.
// الوسائط: [org_id] [status]. تطبع RESULT=SUCCESS|REJECTED|ERROR.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\CRM\Actions\CreateClient;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Domain\Billing\Exceptions\EntitlementLimitExceeded;

$orgId = (int) ($argv[1] ?? 0);
$status = $argv[2] ?? 'active';
try {
    TenantContext::bypass(true);
    $org = Organization::findOrFail($orgId);
    $actor = User::first();
    app(CreateClient::class)->handle($org, ['display_name' => 'C' . getmypid(), 'type' => 'company', 'status' => $status], $actor);
    fwrite(STDOUT, "RESULT=SUCCESS\n");
} catch (EntitlementLimitExceeded $e) {
    fwrite(STDOUT, "RESULT=REJECTED\n");
} catch (\Throwable $e) {
    fwrite(STDOUT, "RESULT=ERROR:" . $e->getMessage() . "\n");
}
