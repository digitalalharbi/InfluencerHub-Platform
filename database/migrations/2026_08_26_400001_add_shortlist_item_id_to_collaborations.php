<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N6 — أثر رجعي دائم من بند ترشيح معتمَد إلى تعاون التنفيذ الناتج عنه.
 *
 * إضافيّ فقط: عمود nullable + فهرس فريد (tenant_id, shortlist_item_id) يضمن
 * تحويلًا واحدًا لكل بند (idempotency على مستوى القاعدة — لا تحويل مزدوج ولو تزامن
 * نقر). لا يمسّ التعاونات القائمة (تبقى shortlist_item_id = null، وNULLها متمايزة في
 * Postgres فلا يتعارض الفريد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaborations', function (Blueprint $table) {
            $table->foreignId('shortlist_item_id')->nullable()->after('deliverable_id')
                ->constrained('campaign_shortlist_items')->nullOnDelete();
            $table->unique(['tenant_id', 'shortlist_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('collaborations', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'shortlist_item_id']);
            $table->dropConstrainedForeignId('shortlist_item_id');
        });
    }
};
