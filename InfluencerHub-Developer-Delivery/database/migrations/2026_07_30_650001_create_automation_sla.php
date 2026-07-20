<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محرّك الأتمتة/SLA: أعمدة تتبّع (لمنع التكرار) على طلبات الخدمة + سجلّ إجراءات آلية قابل للتدقيق.
 * داخلي بالكامل (لا اعتمادات خارجية).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $t) {
            $t->timestamp('sla_reminded_at')->nullable();   // ذُكّر قبل الاستحقاق (مرة)
            $t->timestamp('sla_breached_at')->nullable();    // تجاوز SLA (مرة)
        });

        Schema::create('automation_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('rule', 40);                          // sla.reminder | sla.breach
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->string('detail')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'rule']);
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_log');
        Schema::table('service_requests', function (Blueprint $t) {
            $t->dropColumn(['sla_reminded_at', 'sla_breached_at']);
        });
    }
};
