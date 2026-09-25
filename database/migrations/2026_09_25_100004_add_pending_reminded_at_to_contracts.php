<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علامة تذكير توقيع الطرف على العقد المُرسَل — لمنع التكرار (تُرسَل مرّة واحدة).
 *
 * نمط مطابق لعلامات SLA وتذكير الفاتورة/النشر/ردّ المؤثر/قرار العميل: وجود القيمة
 * يعني «ذُكِّر بالفعل». مبنيّ على sent_at الحقيقيّة (وقت إرسال العقد للطرف).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $t) {
            $t->timestamp('pending_reminded_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $t) {
            $t->dropColumn('pending_reminded_at');
        });
    }
};
