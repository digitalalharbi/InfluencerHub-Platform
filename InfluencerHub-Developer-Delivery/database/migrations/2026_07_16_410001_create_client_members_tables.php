<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('client_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('role', 40); // client_admin|client_campaign_manager|client_content_reviewer|client_finance|client_report_viewer|client_member
            $t->string('status', 20)->default('invited'); // invited|active|suspended|revoked
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('invited_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('suspended_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            // منع تكرار عضوية فعالة لنفس المستخدم/العميل
            $t->unique(['client_id', 'user_id'], 'uniq_client_member');
            $t->index(['tenant_id', 'client_id']);
        });
        Schema::create('client_member_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('email');
            $t->string('role', 40);
            $t->string('token_hash', 64)->unique(); // Hash فقط، لا رمز خام
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'client_id']);
        });
        Schema::create('client_member_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_member_id')->constrained('client_members')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable(); $t->string('to_status', 20);
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('client_member_status_history');
        Schema::dropIfExists('client_member_invitations');
        Schema::dropIfExists('client_members');
    }
};
