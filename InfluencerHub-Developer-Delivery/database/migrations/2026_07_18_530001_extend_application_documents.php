<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('creator_application_documents', function (Blueprint $t) {
            $t->string('stored_name')->nullable()->after('original_name');   // اسم مولّد
            $t->string('extension', 10)->nullable()->after('mime');
            $t->foreignId('uploaded_by')->nullable()->after('checksum_sha256')->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('uploaded')->after('uploaded_by'); // uploaded|transferred|rejected
            $t->softDeletes();
        });
        // إصدارات الملف (استبدال يحتفظ بالتاريخ)
        Schema::create('creator_application_document_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('document_id')->constrained('creator_application_documents')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('path');
            $t->string('checksum_sha256', 64);
            $t->unsignedBigInteger('size_bytes');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        // سجل وصول التنزيل (تدقيق دقيق)
        Schema::create('creator_application_document_access_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('document_id')->constrained('creator_application_documents')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 20);       // download|preview
            $t->string('ip', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'document_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('creator_application_document_access_logs');
        Schema::dropIfExists('creator_application_document_versions');
        Schema::table('creator_application_documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('uploaded_by');
            $t->dropColumn(['stored_name', 'extension', 'status', 'deleted_at']);
        });
    }
};
