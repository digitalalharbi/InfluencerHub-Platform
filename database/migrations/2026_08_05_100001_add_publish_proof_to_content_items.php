<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إثبات النشر ونتائجه.
 *
 * `media_url` هو رابط الأصل الإبداعي (ما سُلّم للمراجعة) لا رابط المنشور الحيّ،
 * فلم يكن في الجدول ما يثبت أن النشر وقع فعلًا ولا ما يقيس أثره — والخطوتان
 * مطلوبتان في الرحلة قبل الفاتورة والتقرير.
 *
 * الأرقام تُدخَل يدويًّا وتُوسَم بمصدرها: لا مزوّد منصّة مربوط باعتمادات، وادّعاء
 * أنها مُزامَنة يكون كذبًا. عند ربط تكامل لاحقًا يتغيّر `results_source` وحده.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $t) {
            // إثبات النشر
            $t->string('published_url', 500)->nullable()->after('published_at');
            $t->string('proof_note', 500)->nullable()->after('published_url');
            $t->foreignId('proof_by')->nullable()->after('proof_note')->constrained('users')->nullOnDelete();
            $t->timestamp('proof_at')->nullable()->after('proof_by');

            // النتائج — كلّها اختيارية: ما لم يُدخَل يبقى فارغًا لا صفرًا
            $t->unsignedBigInteger('reach')->nullable()->after('proof_at');
            $t->unsignedBigInteger('impressions')->nullable()->after('reach');
            $t->unsignedBigInteger('engagements')->nullable()->after('impressions');
            $t->unsignedBigInteger('clicks')->nullable()->after('engagements');
            $t->string('results_source', 16)->nullable()->after('clicks'); // manual | platform
            $t->timestamp('results_at')->nullable()->after('results_source');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('proof_by');
            $t->dropColumn([
                'published_url', 'proof_note', 'proof_at',
                'reach', 'impressions', 'engagements', 'clicks', 'results_source', 'results_at',
            ]);
        });
    }
};
