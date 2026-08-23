<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نطاق التكاملات الحقيقي: اتّصال مُخزَّن لكل (مستأجر، مزوّد، بيئة) بحالة صريحة
 * ورموز مُعمّاة ومسار مزامنة كامل (SyncRun) وأحداث ويبهوك مُعرَّفة بلا تكرار
 * وخريطة كائنات خارجية↔محلية. يكمّل PlatformRegistry الثابت لا يكرّره.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('provider', 40);                    // tiktok|meta|salla|whatsapp_cloud|...
            $t->string('environment', 12)->default('production'); // production|sandbox
            $t->string('status', 24)->default('not_configured');  // انظر IntegrationConnection::STATUSES
            $t->string('external_account_id', 190)->nullable();
            $t->string('external_account_name', 190)->nullable();
            $t->json('scopes')->nullable();
            $t->text('access_token')->nullable();          // مُعمّى (encrypted cast)
            $t->text('refresh_token')->nullable();         // مُعمّى
            $t->timestamp('token_expires_at')->nullable();
            $t->timestamp('last_success_sync_at')->nullable();
            $t->timestamp('last_attempt_sync_at')->nullable();
            $t->timestamp('next_sync_at')->nullable();
            $t->string('last_error', 300)->nullable();     // آمن، بلا أسرار
            $t->timestamp('last_error_at')->nullable();
            $t->string('health', 12)->default('unknown');  // healthy|degraded|error|unknown
            $t->json('capabilities')->nullable();          // capability => status
            $t->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('connected_at')->nullable();
            $t->timestamp('disconnected_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'provider', 'environment']);
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('integration_sync_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained('integration_connections')->cascadeOnDelete();
            $t->string('provider', 40);
            $t->string('capability', 40)->nullable();
            $t->string('type', 16)->default('manual');     // initial|incremental|manual|scheduled|webhook|backfill|reconciliation
            $t->string('status', 12)->default('queued');   // queued|running|success|partial|failed
            $t->string('cursor', 190)->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->unsignedInteger('fetched')->default(0);
            $t->unsignedInteger('created')->default(0);
            $t->unsignedInteger('updated')->default(0);
            $t->unsignedInteger('skipped')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->integer('rate_limit_remaining')->nullable();
            $t->unsignedSmallInteger('retry_count')->default(0);
            $t->string('error', 300)->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'provider', 'status']);
            $t->index(['connection_id', 'created_at']);
        });

        Schema::create('integration_webhook_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $t->string('provider', 40);
            $t->string('event_id', 190)->nullable();       // معرّف المزوّد — لمنع التكرار
            $t->string('type', 60)->nullable();
            $t->boolean('signature_valid')->default(false);
            $t->string('status', 12)->default('received'); // received|processed|skipped|failed
            $t->json('payload')->nullable();
            $t->string('error', 300)->nullable();
            $t->timestamp('received_at')->useCurrent();
            $t->timestamp('processed_at')->nullable();
            $t->unique(['provider', 'event_id']);
            $t->index(['tenant_id', 'provider', 'status']);
        });

        Schema::create('external_object_map', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('provider', 40);
            $t->string('external_type', 40);               // account|post|order|ad|...
            $t->string('external_id', 190);
            $t->string('local_type', 60)->nullable();      // FQCN أو وسم
            $t->unsignedBigInteger('local_id')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'provider', 'external_type', 'external_id']);
            $t->index(['local_type', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_object_map');
        Schema::dropIfExists('integration_webhook_events');
        Schema::dropIfExists('integration_sync_runs');
        Schema::dropIfExists('integration_connections');
    }
};
