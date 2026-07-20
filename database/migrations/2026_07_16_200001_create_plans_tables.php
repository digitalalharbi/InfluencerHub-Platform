<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // خطة (كتالوج، غير مستأجرة)
        Schema::create('plans', function (Blueprint $t) {
            $t->id(); $t->string('key', 40)->unique(); $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->string('applies_to_mode', 20)->default('saas'); // saas|dedicated|self_hosted|any
            $t->timestamps();
        });
        // نسخة خطة (تُجمَّد عند الاستخدام — effective dating)
        Schema::create('plan_versions', function (Blueprint $t) {
            $t->id(); $t->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $t->unsignedInteger('version'); $t->boolean('is_active')->default(true);
            $t->boolean('is_locked')->default(false); // تُقفل عند وجود اشتراك يشير إليها
            $t->timestamp('effective_from')->nullable();
            $t->timestamps();
            $t->unique(['plan_id', 'version']);
        });
        // أسعار (عملة غير ثابتة، بالوحدة الصغرى integer)
        Schema::create('plan_prices', function (Blueprint $t) {
            $t->id(); $t->foreignId('plan_version_id')->constrained('plan_versions')->cascadeOnDelete();
            $t->string('currency', 3);                 // لا hardcode
            $t->string('interval', 20);                // monthly|yearly|one_time|custom
            $t->bigInteger('amount_minor')->default(0); // بالهللة/السنت
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        // ميزات (boolean|numeric)
        Schema::create('features', function (Blueprint $t) {
            $t->id(); $t->string('key', 60)->unique(); $t->string('label');
            $t->string('type', 12); // boolean|numeric
            $t->timestamps();
        });
        // Entitlements لكل نسخة خطة
        Schema::create('plan_entitlements', function (Blueprint $t) {
            $t->id(); $t->foreignId('plan_version_id')->constrained('plan_versions')->cascadeOnDelete();
            $t->string('feature_key', 60);
            $t->bigInteger('value')->nullable(); // numeric: الحد؛ boolean: 1/0؛ null+unlimited
            $t->boolean('is_unlimited')->default(false);
            $t->timestamps();
            $t->unique(['plan_version_id', 'feature_key']);
        });
    }
    public function down(): void {
        foreach (['plan_entitlements','features','plan_prices','plan_versions','plans'] as $x) Schema::dropIfExists($x);
    }
};
