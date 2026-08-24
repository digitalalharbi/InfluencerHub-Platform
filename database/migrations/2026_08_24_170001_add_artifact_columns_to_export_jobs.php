<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * يُرقّي export_jobs ليكون مخزن «الأثر» (Document Artifact) الموحّد: نسخة ثابتة
 * واحدة لكل (نوع، كيان، مرشّحات، إصدار قالب) — المعاينة والتنزيل يبثّان نفس
 * البايتات (checksum). fingerprint يُكتشف به تغيّر المصدر (نسخة قديمة/محدثة).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('export_jobs', function (Blueprint $t) {
            $t->string('subject_type', 60)->nullable()->after('type');   // App\...\Campaign
            $t->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $t->string('checksum', 64)->nullable()->after('size_bytes');       // sha256 لبايتات الملفّ
            $t->string('fingerprint', 64)->nullable()->after('checksum');      // sha256 للقطة المصدر+القالب
            $t->string('template_version', 20)->nullable()->after('fingerprint');
            $t->index(['tenant_id', 'subject_type', 'subject_id', 'format']);
        });
    }

    public function down(): void
    {
        Schema::table('export_jobs', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'subject_type', 'subject_id', 'format']);
            $t->dropColumn(['subject_type', 'subject_id', 'checksum', 'fingerprint', 'template_version']);
        });
    }
};
