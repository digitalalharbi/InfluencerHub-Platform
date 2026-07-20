<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void {
        Schema::create('creator_applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('reference', 40)->unique();          // مرجع عشوائي غير قابل للتخمين (لا ID متسلسل)
            $t->string('status', 30)->default('draft');
            $t->string('account_type', 20)->nullable();      // influencer|ugc_creator|both
            // أساسية
            $t->string('full_name')->nullable();
            $t->string('professional_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('whatsapp', 30)->nullable();
            $t->string('country_code', 2)->nullable();
            $t->string('city', 120)->nullable();
            $t->string('gender', 20)->nullable();
            $t->json('languages')->nullable();
            $t->text('bio')->nullable();
            $t->string('avatar_path')->nullable();
            $t->json('categories')->nullable();              // slugs مختارة
            // تقدّم
            $t->unsignedTinyInteger('current_step')->default(1);
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('phone_verified_at')->nullable();
            $t->timestamp('terms_accepted_at')->nullable();
            $t->timestamp('privacy_accepted_at')->nullable();
            // مراجعة
            $t->foreignId('assigned_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('rejection_reason')->nullable();
            // ربط بعد القبول
            $t->foreignId('creator_id')->nullable()->constrained('creators')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // موثوق
            $t->string('mowthooq_license_number')->nullable();
            $t->date('mowthooq_issued_at')->nullable();
            $t->date('mowthooq_expires_at')->nullable();
            $t->string('mowthooq_document_path')->nullable();
            $t->string('mowthooq_status', 20)->default('not_provided'); // not_provided|pending|verified|expired|rejected
            $t->string('mowthooq_rejection_reason')->nullable();
            // مالية (IBAN مشفّر فعليًا؛ نُخزّن آخر 4 فقط للعرض)
            $t->string('beneficiary_name')->nullable();
            $t->string('bank_name')->nullable();
            $t->text('iban_encrypted')->nullable();
            $t->string('iban_last4', 4)->nullable();
            $t->string('iban_document_path')->nullable();
            $t->string('tax_number')->nullable();
            $t->string('financial_verification_status', 20)->default('not_provided');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('creator_application_platforms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('application_id')->constrained('creator_applications')->cascadeOnDelete();
            $t->string('platform', 20);
            $t->string('username', 120)->nullable();
            $t->string('profile_url')->nullable();
            $t->unsignedBigInteger('followers_count')->default(0);
            $t->unsignedBigInteger('average_views')->default(0);
            $t->decimal('engagement_rate', 5, 2)->nullable();
            $t->boolean('is_verified')->default(false);
            $t->string('verification_method', 30)->nullable();
            $t->string('source', 20)->default('applicant');
            $t->string('status', 25)->default('manual_unverified'); // live_api|manual_verified|manual_unverified|waiting_for_credentials|error
            $t->timestamp('last_verified_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'application_id']);
        });

        Schema::create('creator_application_services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('application_id')->constrained('creator_applications')->cascadeOnDelete();
            $t->string('service_type', 30);
            $t->unsignedBigInteger('price_minor')->nullable();
            $t->string('currency', 3)->default('SAR');
            $t->unsignedInteger('delivery_days')->nullable();
            $t->unsignedInteger('revision_rounds')->nullable();
            $t->unsignedInteger('usage_rights_days')->nullable();
            $t->text('description')->nullable();
            $t->boolean('is_available')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'application_id']);
        });

        Schema::create('creator_application_portfolios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('application_id')->constrained('creator_applications')->cascadeOnDelete();
            $t->string('type', 20);            // image|video|link
            $t->string('url')->nullable();
            $t->string('path')->nullable();    // للملفات الخاصة
            $t->string('category', 60)->nullable();
            $t->string('previous_brand')->nullable();
            $t->text('description')->nullable();
            $t->string('status', 20)->default('active');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['tenant_id', 'application_id']);
        });

        Schema::create('creator_application_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('application_id')->constrained('creator_applications')->cascadeOnDelete();
            $t->string('kind', 30);            // avatar|iban|mowthooq|portfolio|other
            $t->string('disk', 40)->default('local');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 120);
            $t->unsignedBigInteger('size_bytes');
            $t->string('checksum_sha256', 64);
            $t->timestamps();
            $t->index(['tenant_id', 'application_id']);
        });

        Schema::create('creator_application_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('application_id')->constrained('creator_applications')->cascadeOnDelete();
            $t->string('sender_type', 20);     // applicant|agency
            $t->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('creator_application_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('application_id')->constrained('creator_applications')->cascadeOnDelete();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('decision', 30);
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        // append-only: سجل الحالة
        Schema::create('creator_application_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('application_id')->constrained('creator_applications')->cascadeOnDelete();
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->text('internal_notes')->nullable();
            $t->text('applicant_message')->nullable();
            $t->uuid('request_id')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
        });

        // رموز التحقق (Hash فقط، انتهاء، محاولات)
        Schema::create('creator_application_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('application_id')->constrained('creator_applications')->cascadeOnDelete();
            $t->string('channel', 10);         // email|phone
            $t->string('code_hash', 64);       // sha256(code) — لا رمز خام
            $t->timestamp('expires_at');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['application_id', 'channel']);
        });
    }
    public function down(): void {
        foreach (['creator_application_verifications','creator_application_status_history','creator_application_reviews',
                  'creator_application_messages','creator_application_documents','creator_application_portfolios',
                  'creator_application_services','creator_application_platforms','creator_applications'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
