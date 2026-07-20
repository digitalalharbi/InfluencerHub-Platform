<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * القدرات في طلب الانضمام.
 *
 * الطلب هو المكان الذي يُصرّح فيه الصانع بنفسه أول مرة، وكان يُجبَر على قائمة
 * منسدلة واحدة (`account_type`). لو تركناها كذلك لعاد التطبيع بلا معنى: الصانع
 * يختار واحدة عند التقديم، ثم عليه أن يعيد وصف نفسه بعد القبول.
 *
 * جدول منفصل هنا مبالغة — الطلب مسودة قصيرة العمر تُنسخ إلى `creators` عند
 * القبول، ولا يُستعلم عنه بالقدرة. لذلك JSON، والتطبيع الحقيقي في
 * `creator_capabilities` بعد القبول.
 *
 * `account_type` يبقى ويُكتب مشتقًّا (كتابة مزدوجة انتقالية) لأن شاشات المراجعة
 * وإنشاء العضوية ما تزال تقرؤه.
 */
return new class extends Migration
{
    private const LEGACY_MAP = [
        'influencer' => ['influencer'],
        'ugc_creator' => ['ugc'],
        'both' => ['influencer', 'ugc'],
    ];

    public function up(): void
    {
        Schema::table('creator_applications', function (Blueprint $t) {
            $t->json('capabilities')->nullable()->after('account_type');
        });

        // لا متقدّم يفقد تصريحه: الطلبات القائمة تُقرأ من النوع القديم
        foreach (DB::table('creator_applications')->select('id', 'account_type')->cursor() as $a) {
            DB::table('creator_applications')->where('id', $a->id)->update([
                'capabilities' => json_encode(self::LEGACY_MAP[$a->account_type] ?? []),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('creator_applications', function (Blueprint $t) {
            $t->dropColumn('capabilities');
        });
    }
};
