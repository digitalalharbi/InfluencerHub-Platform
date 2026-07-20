<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('organization_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('role', 40);                 // من enum الأدوار
            $t->string('status', 20)->default('active'); // active|suspended|invited
            $t->timestamps();
            // مستخدم واحد بدور واحد لكل (organization, workspace)
            $t->unique(['user_id', 'organization_id', 'workspace_id'], 'uniq_member_org_ws');
            $t->index(['tenant_id', 'organization_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('organization_memberships'); }
};
