<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * نوع المستأجر: وكالة أم علامة أم إدارة منصّة.
 *
 * `deployment_mode` يصف **كيف** يُستضاف المستأجر (saas / self-hosted) لا **ما**
 * هو. تحميله معنى ثانيًا يخلط محورين مستقلَّين، فالنوع عمود مستقلّ.
 *
 * كل المستأجرين اليوم وكالات — يُملأ العمود بذلك، فلا يتغيّر سلوك قائم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->string('type', 20)->default('agency')->after('slug');
        });

        // النظام اليوم وكالة-مركزي: كل مستأجر قائم وكالة
        DB::table('tenants')->update(['type' => 'agency']);

        Schema::table('tenants', function (Blueprint $t) {
            $t->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->dropIndex(['type', 'status']);
            $t->dropColumn('type');
        });
    }
};
