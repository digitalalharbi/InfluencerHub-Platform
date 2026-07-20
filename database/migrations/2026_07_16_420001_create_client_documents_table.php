<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('client_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('category', 40)->default('other'); // contract|cr|vat|brief|report|invoice|other
            $t->string('title');
            $t->string('disk', 40)->default('local');       // قرص خاص (storage/app/private)
            $t->string('path');                             // مسار داخلي غير عام
            $t->string('original_name');
            $t->string('mime', 120);
            $t->unsignedBigInteger('size_bytes');
            $t->string('checksum_sha256', 64);              // نزاهة الملف
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tenant_id', 'client_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('client_documents'); }
};
