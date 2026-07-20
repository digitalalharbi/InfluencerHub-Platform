<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الفوترة: فاتورة العميل، بنودها، ومدفوعاتها.
 *
 * كانت مجموعة «المالية» تحوي المستحقات وحدها: يُصرَف للمبدع ولا يُطالَب العميل
 * أبدًا. رحلة التشغيل تنقطع عند «إنشاء فاتورة ← تسجيل الدفع» لغياب الجداول
 * والشيفرة معًا، فلا تُغلق حملة ماليًّا.
 *
 * المبالغ بوحدات صغرى صحيحة (هللات) على نسق `payouts` — لا عدد عشري عائم في
 * المال. والمدفوعات سجلّات مستقلّة لا حقل «مدفوع» واحد: الدفع الجزئي واقع،
 * وتاريخ الاستلام يجب أن يبقى مُتتبَّعًا لا مطموسًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('invoice_number', 40);
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            // draft → issued → (partially_paid) → paid | overdue | cancelled
            $t->string('status', 20)->default('draft');
            $t->string('currency', 3)->default('SAR');

            // الإجماليات محسوبة من البنود ومحفوظة: الفاتورة الصادرة وثيقة ثابتة
            // لا تتغيّر بتغيّر نسبة ضريبة لاحقة.
            $t->bigInteger('subtotal_minor')->default(0);
            $t->bigInteger('discount_minor')->default(0);
            $t->bigInteger('tax_minor')->default(0);
            $t->bigInteger('total_minor')->default(0);
            $t->unsignedSmallInteger('tax_rate_bp')->default(1500); // نقاط أساس: 15٪ = 1500

            $t->date('issue_date')->nullable();
            $t->date('due_date')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->text('notes')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['tenant_id', 'invoice_number']);
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'client_id']);
            $t->index('campaign_id');
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('description', 300);
            $t->integer('quantity')->default(1);
            $t->bigInteger('unit_price_minor');
            $t->bigInteger('line_total_minor');
            // ربط اختياري بالمخرَج: البند يشرح ما يُدفَع مقابله لا مبلغًا مجرّدًا
            $t->foreignId('deliverable_id')->nullable()->constrained('campaign_deliverables')->nullOnDelete();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index('invoice_id');
        });

        Schema::create('invoice_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->bigInteger('amount_minor');
            $t->string('currency', 3)->default('SAR');
            $t->string('method', 30);                    // bank_transfer | cash | cheque | provider
            // مزوّد الدفع: `manual` يعني تسجيلًا يدويًّا لدفعة وقعت خارج النظام،
            // ولا يُقدَّم قطّ على أنه تحصيل أجراه النظام.
            $t->string('provider', 30)->default('manual');
            $t->string('provider_reference', 120)->nullable();
            $t->date('received_at');
            $t->text('note')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index('invoice_id');
            // منع ازدواج التحصيل عند إعادة إرسال webhook من المزوّد نفسه
            $t->unique(['provider', 'provider_reference']);
        });

        Schema::create('invoice_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason', 500)->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();

            $t->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_status_history');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
