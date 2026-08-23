<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وظائف التصدير (سجلّ تاريخ + تنزيل آمن) والتقارير المجدولة (يومي/أسبوعي/شهري).
 * الملفّات تُخزَّن على قرص خاص وتُنزَّل بترخيص — لا روابط عامة متوقّعة.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 60);                        // clients|creators|payouts|clients_report|...
            $t->string('title', 190);
            $t->string('format', 8);                       // csv|xlsx|pdf
            $t->string('status', 12)->default('queued');   // queued|processing|completed|failed|expired
            $t->json('filters')->nullable();
            $t->unsignedInteger('row_count')->nullable();
            $t->string('disk', 20)->nullable();
            $t->string('path', 300)->nullable();           // مسار خاص، لا رابط عام
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->string('error', 300)->nullable();
            $t->foreignId('scheduled_report_id')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('scheduled_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // المستلِم/المالك
            $t->string('report_type', 60);                 // clients_report|payouts|...
            $t->string('name', 160);
            $t->string('format', 8)->default('xlsx');
            $t->json('filters')->nullable();
            $t->string('frequency', 10);                   // daily|weekly|monthly
            $t->string('timezone', 40)->default('Asia/Riyadh');
            $t->string('delivery', 16)->default('in_app'); // in_app|email
            $t->boolean('enabled')->default(true);
            $t->timestamp('last_run_at')->nullable();
            $t->timestamp('next_run_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'enabled', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('export_jobs');
    }
};
