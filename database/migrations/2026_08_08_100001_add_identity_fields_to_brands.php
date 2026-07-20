<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول هُويّة العلامة — ما تُطابَق به عند التسجيل الذاتي.
 *
 * `brands` اليوم تحمل `website` نصًّا حرًّا و`contact_information` بصيغة JSON.
 * ولا يصلح أيّهما مؤشّرَ مطابقة: النصّ الحرّ يجعل
 * `https://Nike.com/` و`nike.com` سجلّين مختلفين، وJSON لا يُفهرَس.
 *
 * فتُضاف هنا الحقول **المُطبَّعة** التي تُبنى عليها قرارات المطابقة:
 *
 * - `email_domain`      نطاق البريد المؤسسي (`nike.com`) — أقوى مؤشّر منفرد
 * - `website_domain`    نطاق الموقع مُطبَّعًا بلا بروتوكول ولا `www`
 * - `normalized_name`   الاسم بلا مسافات ولا تشكيل ولا لواحق شركات
 * - `commercial_registration` السجلّ التجاري — مؤشّر قاطع حين يتوفّر
 *
 * كلّها nullable: السجلّات القائمة لا تملكها، وغيابها يعني «لا مؤشّر» لا
 * «لا تطابق». والمطابقة تُبنى على ما يتوفّر لا على ما يُفترض.
 *
 * ولا فهرس فريد على أيٍّ منها عمدًا: علامتان مشروعتان قد تشتركان في نطاق
 * (شركة أمّ وفرعها)، والتفرّد قرار مراجعة لا قيد قاعدة بيانات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $t) {
            $t->string('email_domain', 190)->nullable()->after('website');
            $t->string('website_domain', 190)->nullable()->after('email_domain');
            $t->string('normalized_name', 190)->nullable()->after('slug');
            $t->string('commercial_registration', 60)->nullable()->after('website_domain');

            $t->index('email_domain');
            $t->index('website_domain');
            $t->index('normalized_name');
            $t->index('commercial_registration');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $t) {
            $t->dropIndex(['email_domain']);
            $t->dropIndex(['website_domain']);
            $t->dropIndex(['normalized_name']);
            $t->dropIndex(['commercial_registration']);
            $t->dropColumn(['email_domain', 'website_domain', 'normalized_name', 'commercial_registration']);
        });
    }
};
