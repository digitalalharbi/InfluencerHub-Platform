<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طبقة مساحة عمل Google (GOOGLE-01). لا تُنشئ بنية اتّصال موازية — تمدّد الأساس القائم:
 * الاتّصال/الرموز في integration_connections، وروابط ملفّات Drive في external_object_map.
 * تُضيف فقط سجلّ أحداث خارجيّة حقيقيّة (رسائل Gmail/أحداث Calendar) يغذّي الخطّ الزمني للمبدع
 * بصفوف فعليّة — لا أحداث مُختلَقة. مُعرَّفة بمعرّف المزوّد لمنع التكرار.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_external_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('provider', 40);                 // google
            $t->string('capability', 40);               // gmail|google_calendar
            $t->string('external_id', 190);             // messageId|eventId
            $t->string('kind', 40);                     // email_in|email_out|meeting|briefing|shoot|content_deadline|publication|rights_expiry|payout_due|event
            $t->foreignId('creator_id')->nullable()->constrained('creators')->nullOnDelete();
            $t->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $t->timestamp('occurred_at')->nullable();
            $t->string('title', 300)->nullable();
            $t->string('summary', 500)->nullable();
            $t->string('link', 500)->nullable();
            $t->string('direction', 12)->nullable();    // inbound|outbound (gmail)
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'provider', 'external_id']);
            $t->index(['tenant_id', 'creator_id', 'occurred_at']);
            $t->index(['tenant_id', 'client_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_external_events');
    }
};
