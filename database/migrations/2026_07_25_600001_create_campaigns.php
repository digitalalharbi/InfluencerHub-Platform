<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * منشئ الحملات: حملة تُشتق (اختياريًا) من طلب خدمة، بميزانية بوحدات صغرى،
 * ومخرجات (deliverables) لكل منصّة/مبدع، وآلة حالة بالأحداث + سجل append-only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('campaign_number')->nullable();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $t->foreignId('source_request_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $t->string('name');
            $t->text('objective')->nullable();
            $t->text('brief')->nullable();
            $t->string('status', 20)->default('draft'); // draft|planning|active|paused|completed|cancelled
            $t->bigInteger('budget_minor')->default(0);  // وحدات صغرى (لا float)
            $t->string('currency', 3)->default('SAR');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'client_id']);
            $t->unique(['tenant_id', 'campaign_number']);
        });

        Schema::create('campaign_deliverables', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $t->foreignId('creator_id')->nullable()->constrained('creators')->nullOnDelete();
            $t->string('platform', 20)->nullable();       // instagram|tiktok|snapchat|youtube|x
            $t->string('type', 20);                        // post|story|reel|video|ugc
            $t->unsignedInteger('quantity')->default(1);
            $t->bigInteger('fee_minor')->nullable();       // الأجر المتفق عليه للمبدع (وحدات صغرى)
            $t->string('currency', 3)->default('SAR');
            $t->date('due_date')->nullable();
            $t->string('status', 20)->default('planned');  // planned|assigned|in_progress|submitted|approved|published|cancelled
            $t->string('notes')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'campaign_id']);
        });

        Schema::create('campaign_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['tenant_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_status_history');
        Schema::dropIfExists('campaign_deliverables');
        Schema::dropIfExists('campaigns');
    }
};
