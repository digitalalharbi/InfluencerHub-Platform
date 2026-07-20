<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مستندات إثبات الملكية المرفوعة مع طلب المطالبة.
 *
 * تُخزَّن على قرص خاصّ (`private`) لا العامّ: المستند سجلّ تجاري أو تفويض
 * موقَّع، ورابطٌ عامّ له يعني تسريبه لمن يخمّن المسار. والوصول يمرّ بمتحكّم
 * يتحقّق من الصلاحية في كل مرّة.
 *
 * ويُحفظ `original_name` منفصلًا عن `path`: اسم الملفّ الذي رفعه المستخدم لا
 * يُستعمل مسارًا أبدًا — وإلّا صار `../../.env` اسمًا مقبولًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_claim_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('claim_request_id')->constrained('brand_claim_requests')->cascadeOnDelete();

            $t->string('type', 40);              // commercial_registration | authorization_letter | trademark | other
            $t->string('path', 255);             // مسار مولَّد، لا يشتقّ من اسم المستخدم
            $t->string('original_name', 255);
            $t->string('mime', 100);
            $t->unsignedBigInteger('size_bytes');

            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index('claim_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_claim_documents');
    }
};
