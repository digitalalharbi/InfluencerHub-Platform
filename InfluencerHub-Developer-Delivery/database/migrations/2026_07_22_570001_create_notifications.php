<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نظام إشعارات محايد للمزوّد: in_app فورًا، وقنوات email/sms عبر محاولات تسليم
 * بحالة صادقة (waiting_for_credentials/sent/failed). يخدم بوابتي العميل والمبدع والوكالة.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 60);                       // brand.approved, document.reviewed, ...
            $t->string('category', 30)->default('general'); // brands|documents|profile|team|billing|general
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('action_url')->nullable();
            $t->json('data')->nullable();
            $t->nullableMorphs('subject');                // subject_type/subject_id (اختياري)
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'user_id', 'read_at']);
        });

        // تفضيلات القنوات لكل مستخدم/فئة. الافتراضي in_app مفعّل.
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('category', 30);
            $t->boolean('in_app')->default(true);
            $t->boolean('email')->default(false);
            $t->boolean('sms')->default(false);
            $t->timestamps();
            $t->unique(['tenant_id', 'user_id', 'category']);
        });

        // محاولات التسليم عبر القنوات — تجعل الحالة صريحة وقابلة للتدقيق.
        Schema::create('notification_delivery_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $t->string('channel', 15);                    // in_app|email|sms
            $t->string('status', 30);                     // sent|failed|skipped|waiting_for_credentials
            $t->string('detail')->nullable();
            $t->timestamp('attempted_at')->useCurrent();
            $t->index(['tenant_id', 'notification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_attempts');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
