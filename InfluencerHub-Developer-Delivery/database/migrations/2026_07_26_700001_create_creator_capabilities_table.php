<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * قدرات صانع المحتوى — تطبيع `creators.type` إلى علاقة متعدّدة.
 *
 * كان النوع نصًّا واحدًا: influencer | ugc_creator | both. وجود قيمة «both»
 * هو الدليل على أن الحقل الواحد لا يكفي: الصانع يجمع قدرات، ولا يُجبَر على
 * اختيار واحدة. ومع كل قدرة جديدة (تصوير، تعليق صوتي، بثّ) كانت ستُضاف قيمة
 * نصية أخرى ثم تركيبة، وهذا لا ينتهي.
 *
 * لا يُحذف العمود `type` هنا: يبقى مصدرًا للقراءة القديمة حتى يثبت التكافؤ،
 * وفق قاعدة عدم إزالة القديم قبل التحقّق. النقل عكوسيّ بالكامل.
 */
return new class extends Migration
{
    /** خريطة النوع القديم → القدرات المكافئة. */
    private const LEGACY_MAP = [
        'influencer' => ['influencer'],
        'ugc_creator' => ['ugc'],
        'both' => ['influencer', 'ugc'],
    ];

    public function up(): void
    {
        Schema::create('creator_capabilities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $t->string('capability', 40);
            $t->boolean('is_enabled')->default(true);
            $t->string('approval_status', 20)->default('approved');
            $t->string('experience_level', 20)->nullable();
            $t->bigInteger('base_rate_minor')->nullable();
            $t->unsignedSmallInteger('delivery_days')->nullable();
            $t->unsignedSmallInteger('included_revisions')->nullable();
            // مصدر السجل: legacy_type يعني مُشتقًّا من العمود القديم لا مُدخَلًا من الصانع
            $t->string('source', 20)->default('manual');
            $t->timestamps();

            $t->unique(['creator_id', 'capability']);
            $t->index(['tenant_id', 'capability']);
        });

        // نقل البيانات القائمة: لا صانع يفقد تصنيفه
        foreach (DB::table('creators')->select('id', 'tenant_id', 'type')->cursor() as $c) {
            foreach (self::LEGACY_MAP[$c->type] ?? [] as $cap) {
                DB::table('creator_capabilities')->insert([
                    'tenant_id' => $c->tenant_id,
                    'creator_id' => $c->id,
                    'capability' => $cap,
                    'is_enabled' => true,
                    'approval_status' => 'approved',
                    'source' => 'legacy_type',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_capabilities');
    }
};
