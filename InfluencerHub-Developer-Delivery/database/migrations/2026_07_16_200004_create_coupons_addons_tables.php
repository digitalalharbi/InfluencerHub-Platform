<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('coupons', function (Blueprint $t) {
            $t->id(); $t->string('code', 40)->unique(); $t->string('type', 20); // percent|fixed
            $t->bigInteger('value'); $t->string('currency', 3)->nullable();
            $t->unsignedInteger('max_redemptions')->nullable();
            $t->unsignedInteger('redeemed_count')->default(0);
            $t->timestamp('expires_at')->nullable(); $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id(); $t->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->timestamp('redeemed_at'); $t->timestamps();
            $t->unique(['coupon_id', 'organization_id']);
        });
        Schema::create('add_ons', function (Blueprint $t) {
            $t->id(); $t->string('key', 60)->unique(); $t->string('label');
            $t->string('feature_key', 60);            // الميزة التي يزيدها/يفعّلها
            $t->bigInteger('grant_value')->nullable(); // زيادة رقمية
            $t->boolean('grant_boolean')->default(false);
            $t->timestamps();
        });
        Schema::create('organization_add_ons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('add_on_id')->constrained('add_ons');
            $t->unsignedInteger('quantity')->default(1);
            $t->string('status', 20)->default('active');
            $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['organization_add_ons','add_ons','coupon_redemptions','coupons'] as $x) Schema::dropIfExists($x);
    }
};
