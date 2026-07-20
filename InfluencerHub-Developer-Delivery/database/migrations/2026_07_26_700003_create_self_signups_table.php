<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التسجيل الذاتي — حالة المسار من البريد حتى تفعيل المساحة.
 *
 * لماذا جدول لا `onboarding_completed` منطقيّة واحدة: المسار يتوقّف ويُستأنف،
 * ويفشل في منتصفه، ويحتاج أن يقول للمستخدم أين هو وما الخطوة التالية. القيمة
 * المنطقية الواحدة لا تحمل شيئًا من ذلك.
 *
 * بلا tenant_id: السجلّ يسبق المستأجر الذي سيُنشئه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_signups', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->string('account_type', 20);                  // agency (المسار الذاتي الكامل)
            $t->string('email', 190);
            $t->string('status', 40)->default('email_verification_pending');
            // رمز التحقّق مُجزّأ لا مخزَّن نصًّا: تسريب الجدول لا يمنح أحدًا حسابًا
            $t->string('verification_code_hash')->nullable();
            $t->timestamp('code_expires_at')->nullable();
            $t->unsignedTinyInteger('verification_attempts')->default(0);
            $t->timestamp('email_verified_at')->nullable();
            $t->json('completed_steps')->nullable();
            $t->foreignId('created_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $t->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();

            $t->index(['email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_signups');
    }
};
