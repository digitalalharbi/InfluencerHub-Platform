<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علامة تذكير التأخّر للفاتورة — لضمان عدم التكرار (تُرسَل مرّة واحدة عند التجاوز).
 *
 * نمط مطابق لعلامات SLA (sla_reminded_at/sla_breached_at): وجود القيمة يعني «أُشعِر
 * بالفعل»، فالماسح المجدول لا يُنتج بريدًا ثانيًا لنفس الفاتورة. لا يخترع موعدًا —
 * يعتمد على due_date الحقيقيّة الموجودة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->timestamp('overdue_notified_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn('overdue_notified_at');
        });
    }
};
