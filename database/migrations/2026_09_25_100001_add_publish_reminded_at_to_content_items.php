<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علامة تذكير موعد النشر للمحتوى — لضمان عدم التكرار (تُرسَل مرّة واحدة).
 *
 * نمط مطابق لعلامات SLA وتذكير الفاتورة: وجود القيمة يعني «ذُكِّر بالفعل»، فالماسح
 * المجدول لا يُنتج تذكيرًا ثانيًا لنفس المحتوى. لا يخترع موعدًا — يعتمد على
 * scheduled_at الحقيقيّة التي حدّدها المستخدم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $t) {
            $t->timestamp('publish_reminded_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $t) {
            $t->dropColumn('publish_reminded_at');
        });
    }
};
