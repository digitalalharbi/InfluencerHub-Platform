<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تسجيل علامة تجارية لنفسها — سجلّ الرحلة قبل وجود المستأجر.
 *
 * الرحلة تمتدّ عبر عدّة طلبات (بريد ← رمز ← جوال ← رمز ← بيانات ← مطابقة)،
 * ولا مستأجر بعد ولا مستخدم. فلا مكان لهذه الحالة إلا جدول خاصّ بها.
 *
 * **لا `tenant_id` هنا** — وهذا مقصود: الجدول يسبق المستأجر، ولو نُطّق لَما
 * أمكن قراءته في الرحلة نفسها (TenantScope مغلق افتراضيًّا).
 *
 * الرموز مُجزَّأة (`bcrypt`) لا نصًّا صريحًا: تسريب نسخة من قاعدة البيانات
 * لا يسلّم رموزًا صالحة. وهذا يخالف `creator_invitations` التي تخزّنها صريحة —
 * وهو الفرق مقصود، لا سهو: تلك رموز دعوة يرسلها موظّف يعرف صاحبها، وهذه
 * بوّابة عامّة يفتحها أيّ أحد.
 *
 * ولكلّ قناة عدّاد محاولات مستقلّ: الحدّ على التخمين لا على المستخدم، فمن
 * أخطأ في رمز البريد لا يُقفَل عليه رمز الجوال.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_signups', function (Blueprint $t) {
            $t->id();

            // مرجع علنيّ يظهر في الرابط — لا يُكشف به المعرّف التسلسلي
            $t->string('reference', 40)->unique();

            $t->string('email', 190);
            $t->string('phone', 30)->nullable();

            // الرموز مُجزَّأة؛ ولكلّ قناة محاولاتها وتاريخ تحقّقها
            $t->string('email_code_hash')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->unsignedSmallInteger('email_attempts')->default(0);

            $t->string('phone_code_hash')->nullable();
            $t->timestamp('phone_verified_at')->nullable();
            $t->unsignedSmallInteger('phone_attempts')->default(0);

            // حدّ الإرسال وفترة التهدئة — يمنعان استعمال البوّابة قناةَ إزعاج
            $t->unsignedSmallInteger('sent_count')->default(1);
            $t->timestamp('last_sent_at')->nullable();

            $t->timestamp('expires_at');

            /**
             * الحالة:
             *  email_pending → phone_pending → details_pending → matching
             *  → provisioned | claim_pending | abandoned
             */
            $t->string('status', 30)->default('email_pending');

            // بيانات المؤسسة والعلامة — تُجمع قبل المطابقة وتُستعمل فيها
            $t->json('organization_data')->nullable();
            $t->json('brand_data')->nullable();

            // نتيجة المطابقة: القرار ودرجته والسجلّ المرشَّح
            $t->string('match_decision', 20)->nullable();   // none | possible | strong
            $t->unsignedSmallInteger('match_score')->nullable();
            $t->json('match_signals')->nullable();          // المؤشّرات التي تطابقت، للتدقيق
            $t->foreignId('matched_brand_id')->nullable()->constrained('brands')->nullOnDelete();

            // ما أنشأته الرحلة — تُملأ عند التزويد وتمنع تكراره
            $t->foreignId('created_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $t->foreignId('created_brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $t->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->string('ip', 45)->nullable();
            $t->timestamps();

            $t->index('email');
            $t->index('status');
            $t->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_signups');
    }
};
