<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('type', 40)->default('legacy_clients');
            $t->string('source_file')->nullable();
            $t->string('status', 20)->default('completed'); // completed|rolled_back
            $t->unsignedInteger('imported_count')->default(0);
            $t->unsignedInteger('skipped_count')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('rolled_back_at')->nullable();
        });
        // ربط العملاء بدفعة الاستيراد (لدعم التراجع الآمن عن دفعة كاملة)
        Schema::table('clients', function (Blueprint $t) {
            $t->foreignId('import_batch_id')->nullable()->after('archived_at')->constrained('import_batches')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('clients', function (Blueprint $t) { $t->dropConstrainedForeignId('import_batch_id'); });
        Schema::dropIfExists('import_batches');
    }
};
