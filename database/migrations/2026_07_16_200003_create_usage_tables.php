<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // تجميع دوري ذرّي (مصدر فرض الحد؛ يُقفل للتزامن)
        Schema::create('usage_aggregates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('feature_key', 60);
            $t->date('period_start'); $t->date('period_end');
            $t->bigInteger('used')->default(0);
            $t->timestamps();
            $t->unique(['organization_id', 'feature_key', 'period_start'], 'uniq_usage_period');
        });
        // سجل مفصّل + idempotency
        Schema::create('usage_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('feature_key', 60);
            $t->bigInteger('amount'); // موجب=استهلاك، سالب=إطلاق
            $t->date('period_start'); $t->date('period_end');
            $t->string('idempotency_key', 80)->nullable();
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['organization_id', 'feature_key', 'idempotency_key'], 'uniq_usage_idem');
            $t->index(['organization_id', 'feature_key', 'period_start']);
        });
    }
    public function down(): void {
        foreach (['usage_records','usage_aggregates'] as $x) Schema::dropIfExists($x);
    }
};
