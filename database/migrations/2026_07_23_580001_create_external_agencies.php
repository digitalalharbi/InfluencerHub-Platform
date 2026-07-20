<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الوكالات الخارجية (الشركاء): كيانات + أعضاء بوابة + دعوات + سجل حالة،
 * وروابط مُنطّقة (scoped) بالعملاء/العلامات. عزل مستأجر fail-closed كبقية الوحدات.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_agencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('agency_number')->nullable();
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->string('status', 20)->default('draft'); // draft|submitted|under_review|approved|suspended|archived
            $t->string('contact_name')->nullable();
            $t->string('contact_email')->nullable();
            $t->string('contact_phone')->nullable();
            $t->string('country_code', 5)->nullable();
            $t->string('website')->nullable();
            $t->string('specialization')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('changes_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->unique(['tenant_id', 'agency_number']);
        });

        Schema::create('external_agency_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('external_agency_id')->constrained('external_agencies')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('role', 30)->default('partner_member'); // partner_admin|partner_member
            $t->string('status', 15)->default('invited');       // active|invited|suspended|revoked
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('invited_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('suspended_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->unique(['external_agency_id', 'user_id']);
            $t->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('external_agency_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('external_agency_id')->constrained('external_agencies')->cascadeOnDelete();
            $t->string('email', 160);
            $t->string('role', 30)->default('partner_member');
            $t->string('token_hash', 64);
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'external_agency_id']);
        });

        Schema::create('external_agency_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('external_agency_id')->constrained('external_agencies')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['tenant_id', 'external_agency_id']);
        });

        // روابط مُنطّقة: تربط شريكًا بعميل (واختياريًا علامة) بنطاقات محدّدة.
        Schema::create('partner_client_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('external_agency_id')->constrained('external_agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained('brands')->cascadeOnDelete();
            $t->json('scopes')->nullable();                     // ['view_briefs','submit_content',...]
            $t->string('status', 15)->default('active');        // active|suspended|revoked
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['external_agency_id', 'client_id', 'brand_id'], 'partner_link_unique');
            $t->index(['tenant_id', 'external_agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_client_links');
        Schema::dropIfExists('external_agency_status_history');
        Schema::dropIfExists('external_agency_invitations');
        Schema::dropIfExists('external_agency_members');
        Schema::dropIfExists('external_agencies');
    }
};
