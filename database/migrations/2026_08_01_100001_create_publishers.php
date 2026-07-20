<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الناشرون (Publisher Intelligence) — حسابات مُكتشَفة/مُحلَّلة على المنصّات، حتى قبل التسجيل في CRM.
 * كل صف يحمل مصدره (source) وتاريخ آخر مزامنة (last_synced_at) — لا أرقام وهمية.
 * التحويل إلى مؤثر يربط converted_creator_id (idempotent) لمنع التكرار.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('publisher_number', 40);
            $t->string('platform', 20);                 // snapchat|tiktok|instagram|youtube|x|linkedin
            $t->string('handle', 160);
            $t->string('display_name', 200)->nullable();
            $t->string('avatar_url', 500)->nullable();
            $t->unsignedBigInteger('followers_count')->default(0);
            $t->decimal('engagement_rate', 5, 2)->nullable();    // %
            $t->decimal('growth_30d', 6, 2)->nullable();         // % آخر 30 يوم
            $t->json('content_types')->nullable();               // ['reel','story',...]
            $t->json('categories')->nullable();                  // ['fashion','beauty',...]
            $t->json('brands_worked_with')->nullable();          // أسماء علامات مُكتشَفة (عند توفّر البيانات رسميًا)
            $t->string('city', 120)->nullable();
            $t->string('language', 10)->nullable();
            $t->string('audience_note', 500)->nullable();
            $t->unsignedTinyInteger('quality_score')->nullable(); // 0..100 موثوقية/جودة
            $t->string('source', 20)->default('manual');         // manual|import|sandbox|live
            $t->timestamp('last_synced_at')->nullable();
            $t->boolean('saved')->default(false);                // محفوظ في قائمة المستخدم
            $t->foreignId('converted_creator_id')->nullable();   // ربط بعد التحويل (منع التكرار)
            $t->foreignId('created_by')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'platform']);
            $t->index(['tenant_id', 'saved']);
            $t->unique(['tenant_id', 'platform', 'handle']);     // لا تكرار لنفس الحساب داخل المستأجر
        });

        Schema::table('creators', function (Blueprint $t) {
            $t->foreignId('publisher_id')->nullable()->after('user_id'); // مصدر التحويل من الناشرين
        });
    }

    public function down(): void
    {
        Schema::table('creators', fn (Blueprint $t) => $t->dropColumn('publisher_id'));
        Schema::dropIfExists('publishers');
    }
};
