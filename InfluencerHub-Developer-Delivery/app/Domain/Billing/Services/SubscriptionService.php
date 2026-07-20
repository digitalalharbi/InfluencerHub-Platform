<?php
namespace App\Domain\Billing\Services;
use App\Domain\Billing\Exceptions\InvalidSubscriptionTransition;
use App\Domain\Billing\Models\{Subscription, SubscriptionEvent};

class SubscriptionService {
    /** انتقالات الحالة المسموحة (state machine صارم). */
    private const TRANSITIONS = [
        'incomplete' => ['trialing','active','expired'],
        'trialing'   => ['active','cancelled','expired','past_due'],
        'active'     => ['past_due','paused','cancelled','expired'],
        'past_due'   => ['active','cancelled','expired'],
        'paused'     => ['active','cancelled','expired'],
        'cancelled'  => [],
        'expired'    => [],
    ];

    public function transition(Subscription $sub, string $to, array $data = []): Subscription {
        $from = $sub->status;
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidSubscriptionTransition("انتقال غير مسموح: {$from} → {$to}");
        }
        $sub->update(['status' => $to]);
        SubscriptionEvent::create(['subscription_id' => $sub->id, 'type' => "status.{$to}", 'data' => ['from' => $from] + $data, 'occurred_at' => now()]);
        return $sub->refresh();
    }

    public function canTransition(Subscription $sub, string $to): bool {
        return in_array($to, self::TRANSITIONS[$sub->status] ?? [], true);
    }
}
