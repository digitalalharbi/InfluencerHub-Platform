<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // ملف الفوترة (واحد لكل عميل) — لا float؛ المبالغ/الشروط أعداد صحيحة
        Schema::create('client_billing_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete()->unique();
            $t->string('billing_name')->nullable();
            $t->string('billing_email')->nullable();
            $t->string('billing_contact_name')->nullable();
            $t->string('billing_contact_phone', 30)->nullable();
            $t->string('tax_number')->nullable();
            $t->boolean('vat_registered')->default(false);
            $t->string('billing_address')->nullable();
            $t->boolean('purchase_order_required')->default(false);
            $t->string('default_currency', 3)->default('SAR');
            $t->text('invoice_notes')->nullable();
            $t->unsignedInteger('payment_terms_days')->default(0);
            $t->timestamps();
        });
        // طلبات تعديل البيانات القانونية الحساسة (تُراجَع قبل التطبيق)
        Schema::create('client_profile_change_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('changes');                       // {field: {old, new}}
            $t->string('status', 20)->default('submitted'); // draft|submitted|under_review|approved|changes_requested|rejected|cancelled
            $t->string('reviewer_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'client_id', 'status']);
        });
        Schema::create('client_profile_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('change_request_id')->constrained('client_profile_change_requests')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
        });
        // حقول ملف إضافية على العميل (إن لم تكن موجودة)
        Schema::table('clients', function (Blueprint $t) {
            if (! Schema::hasColumn('clients', 'logo_path')) $t->string('logo_path')->nullable();
            if (! Schema::hasColumn('clients', 'whatsapp')) $t->string('whatsapp', 30)->nullable();
            if (! Schema::hasColumn('clients', 'billing_email')) $t->string('billing_email')->nullable();
            if (! Schema::hasColumn('clients', 'billing_contact_name')) $t->string('billing_contact_name')->nullable();
            if (! Schema::hasColumn('clients', 'billing_contact_phone')) $t->string('billing_contact_phone', 30)->nullable();
        });
    }
    public function down(): void {
        Schema::table('clients', function (Blueprint $t) {
            foreach (['logo_path','billing_email','billing_contact_name','billing_contact_phone'] as $c) {
                if (Schema::hasColumn('clients', $c)) $t->dropColumn($c);
            }
        });
        Schema::dropIfExists('client_profile_status_history');
        Schema::dropIfExists('client_profile_change_requests');
        Schema::dropIfExists('client_billing_profiles');
    }
};
