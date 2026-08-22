<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توصيات مدير النظام لعميل بعينه — مبدعون محوَّلون من قاعدته.
 *
 * نسخة (snapshot) لا مرجع: مدير النظام قد يحذف قاعدته بالكامل قبل استعراض
 * النظام، فلو كانت التوصية مرتبطة بمفتاح خارجي لانهارت. النسخة تُبقي ما وصل
 * العميل قائمًا.
 *
 * بلا جوّال: التواصل مع المبدع شأن المدير، لا يُكشَف للعميل. «ليست لأي أحد آخر».
 *
 * مربوطة بالمستأجر: العميل يتبع مستأجر وكالة، والتوصية داخل نطاقه (fail-closed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_pool_recommendations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();

            // نسخة الحقول التي يراها العميل (بلا جوّال)
            $t->string('name', 190);
            $t->string('platform', 30);
            $t->string('account_url', 500)->nullable();
            $t->unsignedBigInteger('followers')->nullable();
            $t->jsonb('categories')->nullable();
            $t->bigInteger('price_minor')->nullable();       // السعر المعروض للعميل
            $t->string('region', 60)->nullable();
            $t->string('city', 60)->nullable();
            $t->string('source_type', 20)->default('celebrity');

            // قرار العميل
            $t->string('status', 20)->default('recommended'); // recommended | approved | rejected
            $t->string('decision_reason', 500)->nullable();
            $t->timestamp('decided_at')->nullable();

            $t->foreignId('recommended_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['tenant_id', 'client_id', 'status']);
            $t->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_pool_recommendations');
    }
};
