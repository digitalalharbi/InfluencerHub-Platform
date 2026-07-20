<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلبات الخدمة الخارجية: يرفعها العميل أو الشريك للوكالة، تُدار بآلة حالة مدفوعة بالأحداث
 * مع SLA (due_at حسب الأولوية)، مناقشة (ملاحظات داخلية/خارجية)، وسجل حالة append-only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('request_number')->nullable();
            // مصدر الطلب: عميل أو شريك
            $t->string('requester_type', 12);                 // client|partner
            $t->foreignId('requester_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $t->foreignId('requester_agency_id')->nullable()->constrained('external_agencies')->nullOnDelete();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // موضوع الطلب
            $t->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $t->string('type', 20);                           // campaign|content|report|consultation|other
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('priority', 10)->default('normal');    // low|normal|high|urgent
            $t->string('status', 20)->default('submitted');   // submitted|triage|in_progress|needs_info|resolved|closed|cancelled
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('due_at')->nullable();              // SLA
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'requester_type', 'requester_client_id']);
            $t->index(['tenant_id', 'requester_agency_id']);
            $t->unique(['tenant_id', 'request_number']);
        });

        Schema::create('service_request_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('service_request_id')->constrained('service_requests')->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('author_type', 12);                    // agency|client|partner
            $t->text('body');
            $t->boolean('is_internal')->default(false);       // ملاحظة داخلية للوكالة فقط
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'service_request_id']);
        });

        Schema::create('service_request_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('service_request_id')->constrained('service_requests')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['tenant_id', 'service_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_status_history');
        Schema::dropIfExists('service_request_comments');
        Schema::dropIfExists('service_requests');
    }
};
