<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * العقود: اتفاقية تُصدرها الوكالة لمبدع أو عميل (اختياريًا مشتقّة من تعاون/حملة)،
 * تمرّ بدورة حياة، ويقبلها الطرف المقابل داخل بوابته (تسجيل قبول + اسم + وقت — ليست توقيعًا قانونيًا خارجيًا).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('contract_number')->nullable();
            $t->string('party_type', 12);                  // creator|client
            $t->foreignId('creator_id')->nullable()->constrained('creators')->nullOnDelete();
            $t->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $t->foreignId('collaboration_id')->nullable()->constrained('collaborations')->nullOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $t->string('title');
            $t->longText('terms')->nullable();
            $t->bigInteger('value_minor')->default(0);
            $t->string('currency', 3)->default('SAR');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('status', 16)->default('draft');    // draft|sent|signed|active|completed|terminated|cancelled
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('signed_at')->nullable();
            $t->string('signed_by_name')->nullable();      // اسم مَن قبِل من طرف الطرف المقابل
            $t->foreignId('signed_by_user')->nullable()->constrained('users')->nullOnDelete();
            $t->string('termination_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'party_type']);
            $t->index(['tenant_id', 'creator_id']);
            $t->index(['tenant_id', 'client_id']);
            $t->unique(['tenant_id', 'contract_number']);
        });

        Schema::create('contract_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->string('from_status', 16)->nullable();
            $t->string('to_status', 16);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_type', 12)->default('agency'); // agency|creator|client
            $t->string('reason')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['tenant_id', 'contract_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_status_history');
        Schema::dropIfExists('contracts');
    }
};
