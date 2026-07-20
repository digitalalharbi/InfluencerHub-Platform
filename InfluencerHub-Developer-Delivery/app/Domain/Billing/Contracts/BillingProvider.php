<?php
namespace App\Domain\Billing\Contracts;
/** عقد موحّد لمزود الدفع — الدومين لا يرتبط بمزود واحد. */
interface BillingProvider {
    public function key(): string;
    /** ينشئ عميل فوترة لدى المزود ويعيد المرجع. */
    public function createCustomer(array $data): string;
    /** ينشئ اشتراكًا لدى المزود ويعيد المرجع. */
    public function createSubscription(string $customerRef, array $items): string;
    public function cancelSubscription(string $subscriptionRef): void;
    /** هل هو Live فعلي (Credentials + Sandbox)؟ */
    public function isLive(): bool;
}
