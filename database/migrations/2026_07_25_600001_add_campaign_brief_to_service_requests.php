<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * موجز الحملة داخل الطلب.
 *
 * السبب: الطلب كان يحمل العنوان والوصف فقط، فالحملة المُشتَقّة منه تبدأ
 * بميزانية صفر وبلا علامة ولا تواريخ ولا منصّات — فيُعيد المستخدم إدخال
 * ما سبق أن قاله العميل في طلبه. هذه الحقول تُلتقط مرّة واحدة عند الطلب
 * وتنتقل إلى الحملة عند التحويل.
 *
 * كلّها اختيارية: الطلب غير الحملي (استشارة/دعم) لا يحتاجها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $t) {
            $t->bigInteger('budget_minor')->nullable()->after('description');   // وحدات صغرى (هللات)
            $t->string('currency', 3)->nullable()->after('budget_minor');
            $t->date('preferred_start_date')->nullable()->after('currency');
            $t->date('preferred_end_date')->nullable()->after('preferred_start_date');
            $t->jsonb('platforms')->nullable()->after('preferred_end_date');    // مفاتيح من PlatformRegistry
            $t->text('scope_notes')->nullable()->after('platforms');            // نطاق العمل المطلوب
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $t) {
            $t->dropColumn(['budget_minor', 'currency', 'preferred_start_date', 'preferred_end_date', 'platforms', 'scope_notes']);
        });
    }
};
