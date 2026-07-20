<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('brands', function (Blueprint $t) {
            $t->string('preferred_language', 10)->nullable();
            $t->json('prohibited_topics')->nullable();
            $t->json('required_messages')->nullable();
            $t->text('visual_guidelines')->nullable();
            $t->json('contact_information')->nullable();
            $t->unsignedInteger('current_version')->default(1);
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('changes_reason')->nullable();
        });
        Schema::create('brand_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->uuid('request_id')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['tenant_id', 'brand_id']);
        });
        Schema::create('brand_review_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('decision', 30);       // approved|changes_requested|suspended|archived
            $t->text('note')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('brand_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');             // لقطة بيانات العلامة عند الإرسال
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('brand_social_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $t->string('platform', 20);
            $t->string('handle', 120);
            $t->string('url')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('brand_social_accounts');
        Schema::dropIfExists('brand_versions');
        Schema::dropIfExists('brand_review_decisions');
        Schema::dropIfExists('brand_status_history');
        Schema::table('brands', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reviewed_by');
            $t->dropColumn(['preferred_language','prohibited_topics','required_messages','visual_guidelines','contact_information','current_version','submitted_at','reviewed_at','changes_reason']);
        });
    }
};
