<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('creators', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('creator_number', 30);                 // CR-{tenant}-{seq}
            $t->string('type', 20);                           // influencer|ugc_creator|both
            $t->string('display_name');
            $t->string('handle', 80)->nullable();             // اسم المعرّف العام
            $t->string('email')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('city', 120)->nullable();
            $t->string('country_code', 2)->nullable();
            $t->string('primary_platform', 20)->nullable();   // instagram|tiktok|youtube|snapchat|x
            $t->unsignedBigInteger('followers_count')->default(0);
            $t->json('content_categories')->nullable();
            $t->string('status', 20)->default('prospect');    // prospect|active|paused|blocked
            $t->unsignedBigInteger('rate_per_post_minor')->nullable(); // بالوحدات الصغرى (لا float)
            $t->text('bio')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'creator_number']);
            $t->index(['tenant_id', 'type', 'status']);
        });
        Schema::create('creator_platforms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('creator_id')->constrained('creators')->cascadeOnDelete();
            $t->string('platform', 20);
            $t->string('handle', 120);
            $t->string('url')->nullable();
            $t->unsignedBigInteger('followers_count')->default(0);
            $t->timestamps();
            $t->index(['tenant_id', 'creator_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('creator_platforms');
        Schema::dropIfExists('creators');
    }
};
