<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المحتوى والموافقات: يقدّم المبدع محتوى (اختياريًا ضمن تعاون)، يمرّ بمراجعة الوكالة ثم موافقة العميل،
 * ثم الجدولة/النشر. مراجعات مسجّلة لكل مرحلة + سجل append-only + إصدارات عند إعادة التقديم.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('content_number')->nullable();
            $t->foreignId('collaboration_id')->nullable()->constrained('collaborations')->nullOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $t->foreignId('deliverable_id')->nullable()->constrained('campaign_deliverables')->nullOnDelete();
            $t->foreignId('creator_id')->nullable()->constrained('creators')->nullOnDelete();
            $t->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $t->string('title');
            $t->string('type', 20);                        // post|story|reel|video|ugc
            $t->string('platform', 20)->nullable();
            $t->text('caption')->nullable();
            $t->string('media_url')->nullable();           // رابط المحتوى (يرفعه المبدع)
            $t->string('status', 24)->default('draft');    // draft|submitted|agency_review|changes_requested|client_review|approved|scheduled|published|rejected
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'creator_id']);
            $t->index(['tenant_id', 'client_id']);
            $t->unique(['tenant_id', 'content_number']);
        });

        Schema::create('content_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $t->string('stage', 12);                       // agency|client
            $t->string('decision', 20);                    // approved|changes_requested|rejected
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reviewer_type', 12);               // agency|client
            $t->text('note')->nullable();
            $t->unsignedInteger('content_version')->default(1);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'content_item_id']);
        });

        Schema::create('content_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $t->string('from_status', 24)->nullable();
            $t->string('to_status', 24);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_type', 12)->default('agency'); // agency|client|creator
            $t->string('reason')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['tenant_id', 'content_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_status_history');
        Schema::dropIfExists('content_approvals');
        Schema::dropIfExists('content_items');
    }
};
