<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سعر التكلفة بجانب سعر البيع.
 *
 * الاستيراد الأوّل التقط «سعر البيع» فقط، وأهمل قيمتَي «منزلي» و«تغطية» وهما
 * سعر التكلفة. مدير النظام يحتاج الاثنين للحجز: التكلفة ما يدفعه للمبدع،
 * والبيع ما يُطالِب به العميل. الهامش بينهما قرار تشغيلي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_creator_pool', function (Blueprint $t) {
            $t->bigInteger('cost_post_minor')->nullable()->after('price_post_minor');
            $t->bigInteger('cost_coverage_minor')->nullable()->after('price_coverage_minor');
        });
    }

    public function down(): void
    {
        Schema::table('admin_creator_pool', function (Blueprint $t) {
            $t->dropColumn(['cost_post_minor', 'cost_coverage_minor']);
        });
    }
};
