<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دعوة صانع المحتوى إلى بوابته.
 *
 * 166 من 168 صانع محتوى بلا حساب دخول: المسار الوحيد الذي يُنشئ حسابًا كان
 * قبول طلب انضمام عامّ (`ApproveCreatorApplication`). فمن تُضيفه الوكالة بنفسها
 * يُرشَّح ويُتعاقَد معه ولا يستطيع الدخول ليقبل أو يسلّم أو يوقّع.
 *
 * الرمز يُخزَّن Hash فقط — يُعرض خامًا مرّة واحدة عند الإنشاء كما في دعوة عضو
 * العميل. والتحقّق من البريد والجوال يُسجَّل زمنه لأنه شرط تفعيل البوابة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->string('phone', 32)->nullable();
            $t->string('token_hash', 64)->unique();

            // رمزا تحقّق قصيران — البريد والجوال خطوتان مستقلّتان في الرحلة
            $t->string('email_code', 8)->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('phone_code', 8)->nullable();
            $t->timestamp('phone_verified_at')->nullable();

            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->unsignedSmallInteger('sent_count')->default(1);
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'creator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_invitations');
    }
};
