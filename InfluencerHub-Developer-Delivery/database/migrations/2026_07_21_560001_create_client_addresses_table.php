<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('client_addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('type', 20);            // headquarters|billing|shipping|branch|other
            $t->string('label')->nullable();
            $t->string('recipient_name')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('country_code', 2)->nullable();
            $t->string('region', 120)->nullable();
            $t->string('city', 120)->nullable();
            $t->string('district', 120)->nullable();
            $t->string('street')->nullable();
            $t->string('building_number', 20)->nullable();
            $t->string('postal_code', 20)->nullable();
            $t->string('additional_number', 20)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('is_default')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'client_id', 'type']);
        });
    }
    public function down(): void { Schema::dropIfExists('client_addresses'); }
};
