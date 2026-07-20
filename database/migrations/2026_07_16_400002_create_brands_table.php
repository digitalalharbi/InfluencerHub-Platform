<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('name'); $t->string('slug');
            $t->string('logo_path')->nullable(); $t->string('sector')->nullable();
            $t->string('website')->nullable(); $t->text('description')->nullable();
            $t->string('tone_of_voice')->nullable(); $t->text('target_audience')->nullable();
            $t->string('brand_guidelines_path')->nullable(); $t->string('status', 20)->default('active');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(); $t->softDeletes();
            $t->unique(['tenant_id', 'slug']);
            $t->index(['tenant_id', 'client_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('brands'); }
};
