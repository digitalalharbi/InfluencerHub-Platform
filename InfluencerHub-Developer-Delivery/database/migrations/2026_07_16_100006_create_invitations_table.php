<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $t->string('email');
            $t->string('role', 40);
            $t->string('token', 64)->unique();
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('pending'); // pending|accepted|revoked|expired
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'organization_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('invitations'); }
};
