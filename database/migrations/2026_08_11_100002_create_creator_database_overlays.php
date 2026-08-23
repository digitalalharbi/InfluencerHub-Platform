<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تراكب خاصّ بالمؤسسة على مبدعي قاعدة المؤثرين (بيانات العلاقة الخاصّة).
 *
 * البيانات العالمية (ملف المبدع) تبقى عالمية في admin_creator_pool؛ أمّا ما يخصّ
 * علاقة المؤسسة — مفضّلة، وسوم، ملاحظات، سعر متفاوَض عليه، حالة العلاقة، آخر تواصل —
 * فيُخزَّن هنا معزولًا بالمستأجر/المؤسسة. لا يُكتَب أيّ من هذا على الملف العالمي،
 * ولا تراه مؤسسة أخرى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_database_overlays', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->unsignedBigInteger('pool_creator_id');
            $t->boolean('favorite')->default(false);
            $t->jsonb('tags')->nullable();                     // وسوم خاصّة بالمؤسسة
            $t->text('notes')->nullable();                     // ملاحظات خاصّة
            $t->bigInteger('negotiated_rate_minor')->nullable(); // سعر متفاوَض عليه (خاصّ)
            $t->string('relationship_status', 30)->nullable(); // مثل: prospect|contacted|working
            $t->string('tenant_rating', 20)->nullable();       // تقييم المؤسسة الخاصّ
            $t->unsignedBigInteger('assigned_to')->nullable(); // موظّف المؤسسة المسند إليه
            $t->timestamp('last_contacted_at')->nullable();
            $t->timestamps();

            // تراكب واحد لكل مبدع قاعدة داخل المؤسسة
            $t->unique(['organization_id', 'pool_creator_id']);
            $t->index(['tenant_id', 'pool_creator_id']);
            $t->index(['organization_id', 'favorite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_database_overlays');
    }
};
