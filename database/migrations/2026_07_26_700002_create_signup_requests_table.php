<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلبات فتح الحساب من الموقع العام (عميل/وكالة).
 *
 * لماذا طلب يُراجَع لا تفعيل فوري: فتح مساحة وكالة يستلزم اختيار باقة وتحصيلًا
 * ماليًّا، والمزوّد المالي غير مربوط بعد. التفعيل الفوري هنا كان سيكون واجهة
 * تدّعي ما لا يحدث. الطلب سجلّ حقيقي يُراجَع ويُحوَّل إلى مستأجر عند الاعتماد.
 *
 * بلا tenant_id: هذه السجلّات تسبق وجود المستأجر نفسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_requests', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->string('account_type', 20);            // client | agency
            $t->string('contact_name', 120);
            $t->string('email', 190);
            $t->string('phone', 40)->nullable();
            $t->string('company_name', 190);
            $t->string('website', 190)->nullable();
            $t->string('country_code', 2)->nullable();
            $t->string('team_size', 30)->nullable();
            $t->string('monthly_campaigns', 30)->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 30)->default('submitted'); // submitted|contacted|approved|rejected
            $t->text('review_notes')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('created_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();

            $t->index(['account_type', 'status']);
            $t->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_requests');
    }
};
