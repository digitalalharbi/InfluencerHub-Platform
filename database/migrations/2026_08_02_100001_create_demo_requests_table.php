<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلبات العرض التوضيحي من الموقع العام.
 *
 * لماذا جدول مستقل لا `signup_requests`: طلب العرض ليس نيّة فتح حساب بل نيّة
 * رؤية المنتَج قبل القرار — يصله فريق مختلف، ويحمل حقولًا لا معنى لها في
 * التسجيل (الوقت المفضّل، ما يريد رؤيته). دمجهما كان سيفرض `account_type`
 * ثالثًا يجعل مسار `/register/{type}` قابلًا للتوجيه بنوع لا يوجد له تسجيل.
 *
 * بلا tenant_id: الطلب يسبق وجود المستأجر، كما في طلبات فتح الحساب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->string('audience', 20);               // client | agency | creator
            $t->string('contact_name', 120);
            $t->string('email', 190);
            $t->string('phone', 40)->nullable();
            $t->string('company_name', 190)->nullable(); // صانع المحتوى قد لا يمثّل جهة
            $t->string('role_title', 120)->nullable();
            $t->string('team_size', 30)->nullable();
            $t->string('preferred_time', 30)->nullable(); // صباحًا|بعد الظهر|مساءً
            $t->text('interests')->nullable();            // ما يريد رؤيته تحديدًا
            $t->string('status', 30)->default('submitted'); // submitted|scheduled|done|cancelled
            $t->timestamp('scheduled_at')->nullable();
            $t->text('internal_notes')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();

            $t->index(['audience', 'status']);
            $t->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
