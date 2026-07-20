<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // تصنيفات قابلة للإدارة من لوحة النظام (لا تُثبَّت في الواجهة)
        Schema::create('creator_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete(); // null = عام
            $t->string('slug', 60);
            $t->string('name_ar', 120);
            $t->string('name_en', 120);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'slug']);
        });
    }
    public function down(): void { Schema::dropIfExists('creator_categories'); }
};
