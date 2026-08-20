<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قاعدة مبدعين لمدير النظام وحده — للترشيح والتحويل إلى العملاء.
 *
 * جدول عامّ (بلا tenant_id): لا يخصّ مستأجرًا، بل مصدر ترشيح مركزيّ يراه مدير
 * النظام فقط، ويُحوّل منه ما يشاء إلى عميل بعينه.
 *
 * لا يحوي أيّ حقل مستبعَد: لا اسم مصدر/علامة، ولا رقم ترخيص (ملكية فكرية)،
 * ولا بيانات بنكية، ولا اسم موظّف، ولا شحنات. البيانات المستبقاة تخصّ الترشيح
 * وحده.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_creator_pool', function (Blueprint $t) {
            $t->id();
            $t->string('name', 190);
            $t->string('phone', 40)->nullable();
            $t->string('platform', 30);                 // snapchat | tiktok | linkedin | x
            $t->string('account_url', 500)->nullable();
            $t->unsignedBigInteger('followers')->nullable();
            $t->string('tier', 4)->nullable();          // A | B | C
            $t->string('gender', 10)->nullable();       // male | female
            $t->jsonb('categories')->nullable();        // مجالات المحتوى
            $t->bigInteger('price_post_minor')->nullable();     // سعر المنشور (وحدات صغرى)
            $t->bigInteger('price_coverage_minor')->nullable(); // سعر التغطية
            $t->boolean('shows_face')->nullable();
            $t->string('region', 60)->nullable();
            $t->string('city', 60)->nullable();
            $t->string('rating', 20)->nullable();       // ممتاز | جيد | سئ
            $t->unsignedBigInteger('likes')->nullable();     // UGC
            $t->string('store', 120)->nullable();            // UGC — المتجر
            $t->string('source_type', 20)->default('celebrity'); // celebrity | ugc
            $t->timestamp('imported_at')->nullable();
            $t->timestamps();

            // منع التكرار: نفس الحساب على نفس المنصّة سجلّ واحد
            $t->unique(['platform', 'account_url']);
            $t->index(['platform', 'tier']);
            $t->index('source_type');
            $t->index('region');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_creator_pool');
    }
};
