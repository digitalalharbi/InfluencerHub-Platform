<?php
namespace App\Domain\Billing\Providers;
use App\Domain\Billing\Contracts\BillingProvider;
use Illuminate\Support\Str;
/** مزود وهمي للاختبارات والتطوير — لا يتصل بأي مزود حقيقي وليس Live إطلاقًا. */
class FakeBillingProvider implements BillingProvider {
    public function key(): string { return 'fake'; }
    public function createCustomer(array $data): string { return 'fake_cus_' . Str::random(10); }
    public function createSubscription(string $customerRef, array $items): string { return 'fake_sub_' . Str::random(10); }
    public function cancelSubscription(string $subscriptionRef): void {}
    public function isLive(): bool { return false; }
}
