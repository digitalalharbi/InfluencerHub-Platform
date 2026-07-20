<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('client_notes', function (Blueprint $t) { // داخلية فقط
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body'); $t->timestamps();
            $t->index(['tenant_id', 'client_id']);
        });
        Schema::create('client_status_history', function (Blueprint $t) { // append-only
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable(); $t->string('to_status', 20);
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'client_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('client_status_history'); Schema::dropIfExists('client_notes'); }
};
