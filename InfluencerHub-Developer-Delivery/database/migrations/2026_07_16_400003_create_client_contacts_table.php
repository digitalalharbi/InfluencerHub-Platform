<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('client_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('name'); $t->string('job_title')->nullable(); $t->string('department')->nullable();
            $t->string('email')->nullable(); $t->string('phone', 30)->nullable(); $t->string('whatsapp', 30)->nullable();
            $t->boolean('is_primary')->default(false); $t->string('preferred_channel', 20)->nullable(); $t->text('notes')->nullable();
            $t->timestamps(); $t->softDeletes();
            $t->index(['tenant_id', 'client_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('client_contacts'); }
};
