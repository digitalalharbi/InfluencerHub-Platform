<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إتاحة الميزات المُدارة من المنصّة (Platform-managed feature availability).
 *
 * المصدر الوحيد لقرار «هل الميزة متاحة لهذا النطاق؟». صفٌّ واحد يمثّل قرار إتاحة
 * صريحًا لنطاق مُحدَّد (منصّة عامة / مستأجر / مساحة عمل / بوّابة). غياب الصفّ = الإتاحة
 * الافتراضية (مُتاحة) — فلا تنكسر أي ميزة قائمة، وللمنصّة أن تُعطّلها لأي نطاق.
 *
 * لا تُحذف بيانات الميزة عند التعطيل — هذا الجدول ضبطٌ فقط. إعادة التفعيل تُعيد نفس
 * البيانات المحفوظة كما هي (الثابت: «التعطيل يُخفي ولا يمحو»).
 *
 * الدقّة (الأخصّ يفوز): tenant+workspace+portal ← tenant+workspace ← tenant+portal ←
 * tenant ← global+portal ← global ← الافتراضي (مُتاحة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_availabilities', function (Blueprint $t) {
            $t->id();
            // مفتاح الميزة (منقّط) — مثل influencer_nomination. نطاقٌ عامّ لإعادة الاستخدام.
            $t->string('feature_key', 60);
            // نطاق الإتاحة: null على أي بُعد = «كل القيم» لذلك البُعد.
            $t->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $t->string('portal', 20)->nullable(); // agency|client|creator|partner|brand|admin — null=كل البوّابات
            $t->boolean('enabled')->default(true);
            $t->string('reason', 500)->nullable();
            $t->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            // صفّ واحد لكل نطاق (feature + الأبعاد الثلاثة). NULL في PostgreSQL مميّز،
            // فالتطبيق يستخدم updateOrCreate بمطابقة null صريحة لضمان عدم التكرار.
            $t->unique(['feature_key', 'tenant_id', 'workspace_id', 'portal'], 'feature_avail_scope_unique');
            $t->index(['feature_key', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_availabilities');
    }
};
