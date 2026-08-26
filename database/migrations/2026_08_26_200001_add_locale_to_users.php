<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تفضيل لغة المستخدم — يقود لغة البريد (وغيره لاحقًا).
 *
 * null = اتبع لغة المنصّة الافتراضية (لا نخترع تفضيلًا). القيم المدعومة: ar|en.
 * وجود هذا العمود يجعل البريد يحترم لغة المستقبِل بدل قالب عربي ثابت للجميع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('locale', 5)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('locale');
        });
    }
};
