<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_shortlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $t->unsignedInteger('current_version')->default(0);
            $t->string('status', 30)->default('draft'); // draft|submitted|partially_approved|approved
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['tenant_id', 'campaign_id']);
        });

        Schema::create('campaign_shortlist_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('shortlist_id')->constrained('campaign_shortlists')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('status', 30)->default('draft'); // draft|submitted|approved|partially_approved|changes_requested
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->unique(['shortlist_id', 'version']);
        });

        Schema::create('campaign_shortlist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('shortlist_version_id')->constrained('campaign_shortlist_versions')->cascadeOnDelete();
            $t->foreignId('creator_id')->constrained('creators')->cascadeOnDelete();
            $t->boolean('is_backup')->default(false);
            $t->unsignedBigInteger('proposed_fee_minor')->default(0);
            $t->unsignedSmallInteger('match_score')->default(0);
            $t->json('reasons')->nullable();
            $t->string('client_decision', 20)->default('pending'); // pending|approved|rejected
            $t->string('decision_reason', 500)->nullable();
            $t->timestamps();
            $t->unique(['shortlist_version_id', 'creator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_shortlist_items');
        Schema::dropIfExists('campaign_shortlist_versions');
        Schema::dropIfExists('campaign_shortlists');
    }
};
