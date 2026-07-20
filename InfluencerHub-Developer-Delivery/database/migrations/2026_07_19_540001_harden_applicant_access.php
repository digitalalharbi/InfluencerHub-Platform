<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('creator_applications', function (Blueprint $t) {
            // رمز وصول منفصل عن المرجع (المرجع وحده لا يكفي)
            $t->string('access_token_hash', 64)->nullable();
            $t->timestamp('access_token_expires_at')->nullable();
            $t->timestamp('access_token_revoked_at')->nullable();
            // مصدر حلّ المستأجر (شفافية + منع تجاوز)
            $t->string('workspace_slug')->nullable();
            $t->string('tenant_resolution_source', 30)->nullable(); // slug|subdomain|dedicated|self_hosted
        });
        // حالة نقل كل ملف (post-commit finalization)
        Schema::table('creator_application_documents', function (Blueprint $t) {
            $t->string('transfer_status', 20)->default('pending'); // pending|copying|completed|failed|cancelled
            $t->string('transferred_path')->nullable();
            $t->string('transfer_idempotency_key')->nullable();
            $t->timestamp('transferred_at')->nullable();
        });
        // محاولات وصول فاشلة (تدقيق أمني)
        Schema::create('creator_application_access_attempts', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->nullable();
            $t->string('outcome', 20);   // denied|token_invalid|token_expired|token_revoked|recovered
            $t->string('ip', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('reference');
        });
    }
    public function down(): void {
        Schema::dropIfExists('creator_application_access_attempts');
        Schema::table('creator_application_documents', fn (Blueprint $t) => $t->dropColumn(['transfer_status','transferred_path','transfer_idempotency_key','transferred_at']));
        Schema::table('creator_applications', fn (Blueprint $t) => $t->dropColumn(['access_token_hash','access_token_expires_at','access_token_revoked_at','workspace_slug','tenant_resolution_source']));
    }
};
