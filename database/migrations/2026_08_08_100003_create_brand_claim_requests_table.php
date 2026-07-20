<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المطالبة بعلامة قائمة — الطريق الوحيد إلى سجلٍّ يملكه غيرك.
 *
 * حين يطابق المسجِّل علامةً موجودة، الخيار الآمن الوحيد هو **طلب** يُراجَع،
 * لا ربطٌ تلقائي. الربط التلقائي على تطابق نطاق يعني أن من يملك بريدًا على
 * `nike.com` يرث سجلّ نايك بحملاته وعقوده وفواتيره.
 *
 * ولذلك: الطلب لا يمنح شيئًا حتّى يعتمده مراجع مخوَّل. وحتّى التطابق القويّ
 * يمرّ من هنا — درجته تختصر الأدلّة المطلوبة، لا المراجعة نفسها.
 *
 * **لا `tenant_id`**: الطلب يقع *بين* طرفين — طالبٌ بلا مستأجر بعد، وعلامةٌ
 * في مستأجر قائم. تنطيقه بأحدهما يُعمي الآخر. الحراسة على المراجع: مدير نظام
 * أو من يملك دور التحقّق من العلامات.
 *
 * ومنع التكرار بفهرس جزئي: طلب حيّ واحد لكل (علامة، بريد). وهو **قيد قاعدة
 * بيانات** لا فحص تطبيقي، لأن الفحص في التطبيق يسقط عند طلبين متزامنين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_claim_requests', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();

            $t->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $t->foreignId('signup_id')->nullable()->constrained('brand_signups')->nullOnDelete();

            // الطالب: بريده دائمًا، وحسابه إن كان له حساب
            $t->string('requester_email', 190);
            $t->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();

            /**
             * الحالة:
             *  pending → under_review → approved | rejected | more_info_requested
             *  more_info_requested → under_review
             *  pending|under_review|more_info_requested → expired | cancelled
             */
            $t->string('status', 30)->default('pending');

            // ما ادّعاه الطالب وما أثبته النظام
            $t->json('evidence')->nullable();               // نصّ الادّعاء وروابطه
            $t->json('match_signals')->nullable();           // المؤشّرات التي طابقت وقت الطلب
            $t->unsignedSmallInteger('match_score')->nullable();
            $t->boolean('corporate_email_verified')->default(false);

            // المراجعة
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('decision_reason')->nullable();         // إلزامي عند الرفض
            $t->text('info_requested')->nullable();          // ما طُلب من الطالب

            $t->timestamp('expires_at');
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();

            $t->index(['brand_id', 'status']);
            $t->index(['status', 'expires_at']);
            $t->index('requester_email');
        });

        $this->livePartialUnique();
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_claim_requests');
    }

    /**
     * طلب حيّ واحد لكل (علامة، بريد).
     *
     * القيد في القاعدة لا في التطبيق: فحصٌ تطبيقي «هل يوجد طلب قائم؟» يمرّ منه
     * طلبان متزامنان، فيصير للعلامة الواحدة طلبان يعتمدهما مراجعان مختلفان.
     *
     * والفهرس **جزئي** لأن التفرّد يخصّ الحيّ وحده: من رُفض طلبه له أن يعيد
     * المحاولة بأدلّة أفضل، ومن انتهى طلبه لا يُحبَس عن واحد جديد.
     */
    private function livePartialUnique(): void
    {
        $live = "'pending', 'under_review', 'more_info_requested'";

        // pgsql وsqlite يدعمان الفهرس الجزئي بالصيغة نفسها؛ وغيرهما يُترك بلا
        // قيد بدل أن تنكسر الهجرة على محرّك لا يدعمه.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX brand_claim_live_unique
                 ON brand_claim_requests (brand_id, requester_email)
                 WHERE status IN ({$live})"
            );
        }
    }
};
