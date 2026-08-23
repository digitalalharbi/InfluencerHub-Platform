<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصليب طبقة التسليم: محاولة التسليم تحمل الآن مزوّدًا ومستلِمًا ومعرّف رسالة
 * ومسار حياة كاملًا (queued→sending→sent→delivered→read / failed) وعدّاد إعادة.
 * وتُضاف قناة whatsapp للتفضيلات. كله إضافي — لا يكسر البيانات القائمة.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('notification_delivery_attempts', function (Blueprint $t) {
            $t->string('provider', 40)->nullable()->after('channel');   // in_app|smtp|whatsapp_cloud|...
            $t->string('recipient', 190)->nullable()->after('provider'); // email|E.164|user:{id}
            $t->string('provider_message_id', 190)->nullable()->after('recipient');
            $t->timestamp('queued_at')->nullable()->after('status');
            $t->timestamp('delivered_at')->nullable()->after('queued_at');
            $t->timestamp('read_at')->nullable()->after('delivered_at');
            $t->timestamp('failed_at')->nullable()->after('read_at');
            $t->string('failure_code', 60)->nullable()->after('failed_at');
            $t->unsignedSmallInteger('retry_count')->default(0)->after('failure_code');
            $t->index(['tenant_id', 'channel', 'status']);
            $t->index('provider_message_id');
        });

        Schema::table('notification_preferences', function (Blueprint $t) {
            $t->boolean('whatsapp')->default(false)->after('sms');
        });
    }

    public function down(): void
    {
        Schema::table('notification_delivery_attempts', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'channel', 'status']);
            $t->dropIndex(['provider_message_id']);
            $t->dropColumn(['provider', 'recipient', 'provider_message_id', 'queued_at', 'delivered_at', 'read_at', 'failed_at', 'failure_code', 'retry_count']);
        });
        Schema::table('notification_preferences', function (Blueprint $t) {
            $t->dropColumn('whatsapp');
        });
    }
};
