<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('client_number', 30);
            $t->string('type', 20)->default('company'); // company|brand_owner|government|nonprofit|agency|individual|other
            $t->string('legal_name')->nullable();
            $t->string('display_name');
            $t->string('status', 20)->default('lead'); // lead|qualified|active|inactive|suspended|archived
            $t->string('sector')->nullable();
            $t->string('website')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('whatsapp', 30)->nullable();
            $t->string('country_code', 5)->nullable();
            $t->string('city')->nullable();
            $t->text('address')->nullable();
            $t->string('commercial_registration_number', 30)->nullable();
            $t->date('commercial_registration_expiry')->nullable();
            $t->string('tax_number', 30)->nullable();
            $t->boolean('vat_registered')->default(false);
            $t->string('preferred_language', 5)->default('ar');
            $t->string('acquisition_source')->nullable();
            $t->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'client_number']);
            $t->index(['tenant_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('clients'); }
};
