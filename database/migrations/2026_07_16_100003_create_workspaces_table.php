<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['organization_id', 'slug']);
            $t->index('tenant_id');
        });
    }
    public function down(): void { Schema::dropIfExists('workspaces'); }
};
