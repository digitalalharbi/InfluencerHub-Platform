<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('client_documents', function (Blueprint $t) {
            $t->string('visibility', 20)->default('client_visible')->after('category'); // client_visible|agency_internal
            $t->string('stored_name')->nullable()->after('original_name');
            $t->string('extension', 10)->nullable()->after('mime');
            $t->unsignedInteger('version_number')->default(1)->after('checksum_sha256');
            $t->string('status', 20)->default('pending')->after('version_number'); // pending|under_review|approved|changes_requested|rejected|expired|archived
            $t->foreignId('reviewed_by')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('rejection_reason')->nullable();
            $t->timestamp('expires_at')->nullable();
        });
        Schema::create('client_document_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('document_id')->constrained('client_documents')->cascadeOnDelete();
            $t->unsignedInteger('version_number');
            $t->string('path');
            $t->string('checksum_sha256', 64);
            $t->unsignedBigInteger('size_bytes');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('client_document_access_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('document_id')->constrained('client_documents')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_type', 20);   // client|agency
            $t->string('action', 20);       // download|preview
            $t->string('ip', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'document_id']);
        });
        Schema::create('client_document_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('document_id')->constrained('client_documents')->cascadeOnDelete();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('decision', 30);     // approved|changes_requested|rejected|note
            $t->text('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('client_document_reviews');
        Schema::dropIfExists('client_document_access_logs');
        Schema::dropIfExists('client_document_versions');
        Schema::table('client_documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reviewed_by');
            $t->dropColumn(['visibility','stored_name','extension','version_number','status','reviewed_at','rejection_reason','expires_at']);
        });
    }
};
