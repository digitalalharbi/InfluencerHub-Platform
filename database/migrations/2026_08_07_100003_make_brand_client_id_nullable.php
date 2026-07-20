<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `brands.client_id` يصير اختياريًّا — ولا يُحذف.
 *
 * كان NOT NULL، فلا وجود لعلامة خارج عميل داخل وكالة. العلامة ذاتية التشغيل
 * لا عميل لها، فالقيد كان يمنع التسجيل الذاتي من الجذر.
 *
 * الحقل يبقى **مرجع CRM** للعلامات المُدارة (ولمن يربط علامته بوكالة لاحقًا)،
 * ولم يعد دليل الملكية — الملكية في `brand_workspace_relationships`.
 * لا يُحذف حتّى يكتمل التكافؤ، فحذفه الآن يكسر تقارير وعلاقات قائمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $t) {
            $t->foreignId('client_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // الرجوع يتطلّب ألّا توجد علامة بلا عميل — وإلا فشل القيد بحقّ
        Schema::table('brands', function (Blueprint $t) {
            $t->foreignId('client_id')->nullable(false)->change();
        });
    }
};
