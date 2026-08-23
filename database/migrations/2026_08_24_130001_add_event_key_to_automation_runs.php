<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هوية حدث ثابتة للأتمتة — تمنع تكرار الإجراءات عند إعادة المحاولة (نقرة مزدوجة،
 * إعادة HTTP، إعادة طابور/ويبهوك/مجدول). قاعدة (rule, event_key) الفريدة تضمن
 * تنفيذ الإجراء مرة واحدة لكل حدث فعلي.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('automation_runs', function (Blueprint $t) {
            $t->string('event_key', 190)->nullable()->after('trigger');
            $t->index(['tenant_id', 'rule_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::table('automation_runs', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'rule_id', 'event_key']);
            $t->dropColumn('event_key');
        });
    }
};
