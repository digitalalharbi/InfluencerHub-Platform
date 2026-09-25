<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علامة تذكير ردّ المؤثر على عرض التعاون — لمنع التكرار (تُرسَل مرّة واحدة).
 *
 * نمط مطابق لعلامات SLA وتذكير الفاتورة/النشر: وجود القيمة يعني «ذُكِّر بالفعل»،
 * فالماسح المجدول لا يُنتج تذكيرًا ثانيًا لنفس العرض. مبنيّ على offered_at الحقيقيّة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaborations', function (Blueprint $t) {
            $t->timestamp('response_reminded_at')->nullable()->after('responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('collaborations', function (Blueprint $t) {
            $t->dropColumn('response_reminded_at');
        });
    }
};
