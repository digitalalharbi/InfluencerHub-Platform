<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التعاونات: عرض تعاون من الوكالة لمبدع (اختياريًا مرتبط بحملة/مخرَج)، بدورة حياة بالأحداث
 * يستجيب لها المبدع من بوابته (قبول/رفض/تسليم). الأجر بوحدات صغرى.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('collaborations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('collaboration_number')->nullable();
            $t->foreignId('creator_id')->constrained('creators')->cascadeOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $t->foreignId('deliverable_id')->nullable()->constrained('campaign_deliverables')->nullOnDelete();
            $t->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $t->string('title');
            $t->text('brief')->nullable();
            $t->bigInteger('fee_minor')->default(0);
            $t->string('currency', 3)->default('SAR');
            $t->string('status', 20)->default('offered'); // offered|accepted|declined|in_progress|submitted|approved|completed|cancelled
            $t->date('due_date')->nullable();
            $t->string('decline_reason')->nullable();
            $t->text('submission_note')->nullable();
            $t->timestamp('offered_at')->nullable();
            $t->timestamp('responded_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'creator_id', 'status']);
            $t->index(['tenant_id', 'campaign_id']);
            $t->unique(['tenant_id', 'collaboration_number']);
        });

        Schema::create('collaboration_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('collaboration_id')->constrained('collaborations')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_type', 12)->default('agency'); // agency|creator
            $t->string('reason')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['tenant_id', 'collaboration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_status_history');
        Schema::dropIfExists('collaborations');
    }
};
