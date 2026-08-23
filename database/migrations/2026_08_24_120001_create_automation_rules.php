<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قواعد الأتمتة: محفّز + شروط + إجراءات مُخزَّنة لكل مستأجر. المحرّك حدثي —
 * يُطلَق محفّز فتُقيَّم الشروط وتُنفَّذ الإجراءات (إشعار/مهمة/تصعيد). لا يُسمَح
 * للأتمتة باعتماد مال أو عقد إلّا بسياسة عمل صريحة قائمة.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('key', 80);                          // معرّف مقروء (system.request.assign…)
            $t->string('name', 160);
            $t->string('trigger', 60);                      // request_created|content_submitted|…
            $t->json('conditions')->nullable();             // [{field,op,value}]
            $t->json('actions');                            // [{type, ...}]
            $t->boolean('enabled')->default(true);
            $t->unsignedSmallInteger('priority')->default(100);
            $t->boolean('is_system')->default(false);       // قاعدة افتراضية مُدارة
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
            $t->index(['tenant_id', 'trigger', 'enabled']);
        });

        // تفصيل تشغيلات الأتمتة (يكمّل automation_log المختصر)
        Schema::create('automation_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('rule_id')->nullable()->constrained('automation_rules')->nullOnDelete();
            $t->string('trigger', 60);
            $t->string('status', 12)->default('matched');   // matched|skipped|executed|failed
            $t->json('context')->nullable();
            $t->json('result')->nullable();
            $t->string('error', 300)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'trigger', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_rules');
    }
};
