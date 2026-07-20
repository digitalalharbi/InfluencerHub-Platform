<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('plan_version_id')->constrained('plan_versions');
            $t->string('status', 20)->default('trialing'); // trialing|active|past_due|paused|cancelled|expired|incomplete
            $t->string('billing_provider', 40)->nullable();  // fake|stripe|... (لا hardcode في الدومين)
            $t->string('provider_ref')->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('current_period_start')->nullable();
            $t->timestamp('current_period_end')->nullable();
            $t->json('overrides')->nullable(); // enterprise/dedicated overrides للـentitlements
            $t->timestamps();
            $t->index(['tenant_id', 'organization_id', 'status']);
        });
        Schema::create('subscription_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $t->foreignId('plan_price_id')->constrained('plan_prices');
            $t->unsignedInteger('quantity')->default(1);
            $t->timestamps();
        });
        Schema::create('subscription_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $t->string('type', 40); $t->json('data')->nullable(); $t->timestamp('occurred_at');
            $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['subscription_events','subscription_items','subscriptions'] as $x) Schema::dropIfExists($x);
    }
};
