<?php
namespace App\Domain\Billing\Actions;
use App\Domain\Billing\Models\{PlanVersion, Subscription, SubscriptionEvent};
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;

class CreateSubscription {
    /** ينشئ اشتراكًا (trial افتراضيًا) ويقفل نسخة الخطة (تجميد تاريخي). */
    public function handle(Organization $org, PlanVersion $version, array $attrs = []): Subscription {
        $version->update(['is_locked' => true]); // نسخة مستخدمة → مقفلة
        $sub = TenantContext::withBypass(function () use ($attrs, $org, $version) {
            $sub = Subscription::create(array_merge([
                'tenant_id' => $org->tenant_id,
                'organization_id' => $org->id,
                'plan_version_id' => $version->id,
                'status' => 'trialing',
                'billing_provider' => 'fake',
                'trial_ends_at' => now()->addDays(14),
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ], $attrs));
            SubscriptionEvent::create(['subscription_id' => $sub->id, 'type' => 'created', 'data' => [], 'occurred_at' => now()]);
            return $sub;
        });
        return $sub;
    }
}
