<?php

use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * علاقات العلامة بمساحات العمل — الملكية تُصرَّح لا تُستنتَج.
 *
 * قبل هذا: `brands.tenant_id` هو دليل الملكية الوحيد، فالعلامة مملوكة للوكالة
 * التي أنشأتها ولا وجود لها خارجها. القرار المعتمَد: العلامة تملك نفسها،
 * والوكالة تحصل على **تفويض** قابل للإلغاء.
 *
 * لذلك تصير الملكية صفًّا صريحًا (`owner`) لا حقلًا ضمنيًّا، ويصير ربط الوكالة
 * صفًّا آخر (`managing_agency`) بنطاق خدمات وصلاحيات محدَّد. إلغاء الربط يُنهي
 * الصفّ ولا يمسّ العلامة ولا حملاتها ولا ملفاتها.
 *
 * الترحيل لا يُنشئ نسخة ولا يغيّر معرّفًا: لكل علامة قائمة يُكتب صفّ
 * `managing_agency` مع مستأجرها الحالي، ويبقى `client_id` مرجع CRM كما هو.
 */
return new class extends Migration
{
    /** نطاق الخدمات الافتراضي لعلاقة مُرحَّلة — ما كانت الوكالة تفعله فعلًا. */
    private const LEGACY_SCOPE = ['campaigns', 'shortlists', 'content', 'contracts', 'finance', 'reports'];

    public function up(): void
    {
        Schema::create('brand_workspace_relationships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->constrained()->cascadeOnDelete();
            // مساحة العمل الطرف الآخر: مستأجر علامة (owner) أو وكالة (تفويض)
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // owner | managing_agency | service_provider | collaborator | viewer
            $t->string('relationship_type', 32);
            // pending | active | suspended | ended | rejected
            $t->string('status', 16)->default('pending');

            // ما تراه الوكالة وما تفعله — لا وصول شامل تلقائي
            $t->json('permissions_scope')->nullable();
            $t->json('services_scope')->nullable();

            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['brand_id', 'status']);
            $t->index(['tenant_id', 'status']);
            // علاقة حيّة واحدة من كل نوع بين علامة ومساحة
            $t->unique(['brand_id', 'tenant_id', 'relationship_type'], 'brand_workspace_rel_unique');
        });

        $this->backfill();
    }

    /**
     * ترحيل العلامات القائمة: علاقة إدارة مع مستأجرها الحالي.
     *
     * لا يُنشأ صفّ `owner` هنا: العلامات القائمة مُدارة بوكالة ولا مستأجر علامة
     * لها بعد. تملّكها يقع عند تسجيلها الذاتي أو عند Claim موثَّق — لا بافتراض.
     */
    private function backfill(): void
    {
        $now = now();
        $rows = DB::table('brands')->select('id', 'tenant_id')->get();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('brand_workspace_relationships')->insertOrIgnore(
                $chunk->map(fn ($b) => [
                    'brand_id' => $b->id,
                    'tenant_id' => $b->tenant_id,
                    'relationship_type' => 'managing_agency',
                    'status' => 'active',
                    'permissions_scope' => null,
                    'services_scope' => json_encode(self::LEGACY_SCOPE),
                    'started_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_workspace_relationships');
    }
};
