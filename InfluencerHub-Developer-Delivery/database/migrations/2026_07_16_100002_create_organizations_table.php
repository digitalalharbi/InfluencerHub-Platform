<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('type', 20)->default('agency'); // agency|brand
            $t->string('status', 20)->default('active');
            $t->string('contact_email')->nullable();
            $t->json('settings')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'slug']);
            $t->index(['tenant_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('organizations'); }
};
