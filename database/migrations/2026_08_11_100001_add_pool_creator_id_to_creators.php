<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رابط تقني داخلي: علاقة مبدع المستأجر ↔ مبدع قاعدة المؤثرين المركزية.
 *
 * يُستخدم للتجسيد الحتمي (idempotent): أوّل استخدام لمبدع مشترك من قِبل مؤسسة
 * يُنشئ/يعيد استخدام علاقة مبدع واحدة للمستأجر. الرابط داخليّ بحت — لا يُسلسَل
 * للمستأجر ولا يُعرَض كمصدر تجاري.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $t) {
            $t->unsignedBigInteger('pool_creator_id')->nullable()->after('publisher_id');
            // علاقة واحدة لكل مبدع قاعدة داخل المستأجر (تجسيد حتمي)
            $t->unique(['tenant_id', 'pool_creator_id']);
            $t->index('pool_creator_id');
        });
    }

    public function down(): void
    {
        Schema::table('creators', function (Blueprint $t) {
            $t->dropUnique(['tenant_id', 'pool_creator_id']);
            $t->dropIndex(['pool_creator_id']);
            $t->dropColumn('pool_creator_id');
        });
    }
};
