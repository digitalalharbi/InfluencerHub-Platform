<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('creators', function (Blueprint $t) {
            // ملف كامل
            $t->foreignId('user_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $t->string('professional_name')->nullable()->after('display_name');
            $t->string('whatsapp', 30)->nullable()->after('phone');
            $t->string('gender', 20)->nullable()->after('city');
            $t->json('languages')->nullable()->after('gender');
            // موثوق
            $t->string('mowthooq_license_number')->nullable();
            $t->date('mowthooq_expires_at')->nullable();
            $t->string('mowthooq_status', 20)->default('not_provided');
            // مالية (IBAN مشفّر؛ آخر 4 للعرض فقط)
            $t->string('beneficiary_name')->nullable();
            $t->string('bank_name')->nullable();
            $t->text('iban_encrypted')->nullable();
            $t->string('iban_last4', 4)->nullable();
            $t->string('financial_verification_status', 20)->default('not_provided');
        });
        Schema::create('creator_services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('creator_id')->constrained('creators')->cascadeOnDelete();
            $t->string('service_type', 30);
            $t->unsignedBigInteger('price_minor')->nullable();
            $t->string('currency', 3)->default('SAR');
            $t->unsignedInteger('delivery_days')->nullable();
            $t->unsignedInteger('revision_rounds')->nullable();
            $t->unsignedInteger('usage_rights_days')->nullable();
            $t->text('description')->nullable();
            $t->boolean('is_available')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'creator_id']);
        });
        Schema::create('creator_portfolios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('creator_id')->constrained('creators')->cascadeOnDelete();
            $t->string('type', 20);
            $t->string('url')->nullable();
            $t->string('path')->nullable();
            $t->string('category', 60)->nullable();
            $t->string('previous_brand')->nullable();
            $t->text('description')->nullable();
            $t->string('status', 20)->default('active');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['tenant_id', 'creator_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('creator_portfolios');
        Schema::dropIfExists('creator_services');
        Schema::table('creators', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn(['professional_name','whatsapp','gender','languages','mowthooq_license_number','mowthooq_expires_at','mowthooq_status','beneficiary_name','bank_name','iban_encrypted','iban_last4','financial_verification_status']);
        });
    }
};
