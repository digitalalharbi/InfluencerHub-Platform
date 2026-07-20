<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حالة العلامة الافتراضية تدخل مسار الاعتماد.
 *
 * كان الافتراضي `active` وهي ليست من مفردات المراجعة
 * (draft → submitted → under_review → approved). فأي علامة تُنشأ بمسار لا يمرّ
 * بـ`createDraft` تهبط خارج المسار: لا تظهر في طابور الاعتماد، ولا تصير
 * «معتمدة» أبدًا، وشرط جاهزية الحملة «العلامة معتمدة» يبقى بلا مخرج.
 *
 * أُصلح المستدعي في جولة سابقة، لكن الافتراضي في القاعدة بقي على حاله —
 * فالعيب كامن لأي مسار إنشاء آخر (بذرة، استيراد، أمر console).
 *
 * السجلّات القديمة بحالة `active`: تُنقل إلى `approved` لأن ذلك ما كانت تعنيه
 * عمليًّا (علامة صالحة للاستعمال في الحملات). العكس محفوظ في down().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE brands ALTER COLUMN status SET DEFAULT 'draft'");

        // العلامات القديمة «النشطة» كانت تُستعمل كمعتمَدة فعلًا
        DB::table('brands')->where('status', 'active')->update([
            'status' => 'approved',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE brands ALTER COLUMN status SET DEFAULT 'active'");
        // لا نُعيد كل «approved» إلى «active»: منها ما اعتُمد بالمسار الصحيح.
        // الرجوع يقتصر على الافتراضي، والبيانات تبقى كما هي.
    }
};
