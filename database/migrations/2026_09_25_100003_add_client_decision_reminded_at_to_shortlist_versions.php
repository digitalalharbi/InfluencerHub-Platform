<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علامة تذكير قرار العميل على إصدار الترشيح — لمنع التكرار (تُرسَل مرّة واحدة).
 *
 * نمط مطابق لعلامات SLA وتذكير الفاتورة/النشر/ردّ المؤثر: وجود القيمة يعني «ذُكِّر
 * بالفعل». مبنيّ على submitted_at الحقيقيّة (وقت إرسال الترشيح للعميل).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_shortlist_versions', function (Blueprint $t) {
            $t->timestamp('client_decision_reminded_at')->nullable()->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_shortlist_versions', function (Blueprint $t) {
            $t->dropColumn('client_decision_reminded_at');
        });
    }
};
