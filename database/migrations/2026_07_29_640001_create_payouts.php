<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المستحقات المالية للمبدعين: التزام دفع (اختياريًا مرتبط بتعاون/عقد)، بحالات صادقة.
 * لا تنفيذ دفع فعلي داخل النظام — التسوية الحقيقية عبر مزوّد يُربط لاحقًا (waiting_for_provider).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('payout_number')->nullable();
            $t->foreignId('creator_id')->constrained('creators')->cascadeOnDelete();
            $t->foreignId('collaboration_id')->nullable()->constrained('collaborations')->nullOnDelete();
            $t->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $t->string('description')->nullable();
            $t->bigInteger('amount_minor');                // المبلغ بوحدات صغرى (لا float)
            $t->string('currency', 3)->default('SAR');
            // pending|approved|scheduled|waiting_for_provider|paid|failed|cancelled
            $t->string('status', 24)->default('pending');
            $t->string('iban_last4', 4)->nullable();       // لقطة عند الإنشاء (مصدرها Creator.iban_last4 المشفّر)
            $t->date('due_date')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->string('payment_reference')->nullable();   // مرجع التحويل (يُدخله الموظّف بعد التسوية الحقيقية)
            $t->string('failure_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'creator_id', 'status']);
            $t->unique(['tenant_id', 'payout_number']);
        });

        Schema::create('payout_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('payout_id')->constrained('payouts')->cascadeOnDelete();
            $t->string('from_status', 24)->nullable();
            $t->string('to_status', 24);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['tenant_id', 'payout_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_status_history');
        Schema::dropIfExists('payouts');
    }
};
