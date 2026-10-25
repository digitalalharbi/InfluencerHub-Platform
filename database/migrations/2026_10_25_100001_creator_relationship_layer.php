<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CREATOR-01 — طبقة علاقة المبدع (CRM). حقول علاقة على جدول المبدعين + ملاحظات ومهام.
 * كلها اختيارية/مُنطّقة بالمستأجر؛ «غير معروف ≠ صفر» (NULL يعني غير مُسجَّل، لا قيمة مُختلَقة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $t) {
            $t->string('relationship_stage')->nullable()->after('status');       // prospect|engaged|active|dormant
            $t->string('relationship_health')->nullable()->after('relationship_stage'); // healthy|at_risk|inactive
            $t->foreignId('relationship_owner_id')->nullable()->after('relationship_health')->constrained('users')->nullOnDelete();
            $t->string('source')->nullable()->after('relationship_owner_id');    // كيف وصل المبدع
            $t->string('preferred_channel')->nullable()->after('source');        // whatsapp|email|phone
            $t->string('manager_name')->nullable()->after('preferred_channel');
            $t->string('manager_contact')->nullable()->after('manager_name');
            $t->boolean('do_not_contact')->default(false)->after('manager_contact');
            $t->timestamp('consent_at')->nullable()->after('do_not_contact');
            $t->timestamp('first_contact_at')->nullable()->after('consent_at');
            $t->timestamp('last_interaction_at')->nullable()->after('first_contact_at');
            $t->timestamp('next_follow_up_at')->nullable()->after('last_interaction_at');
            $t->index(['tenant_id', 'next_follow_up_at']);
            $t->index(['tenant_id', 'relationship_owner_id']);
        });

        Schema::create('creator_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('creator_id')->constrained('creators')->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->boolean('pinned')->default(false);
            $t->timestamps();
            $t->index(['tenant_id', 'creator_id', 'created_at']);
        });

        Schema::create('creator_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('creator_id')->constrained('creators')->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title');
            $t->timestamp('due_at')->nullable();
            $t->string('status')->default('open');  // open|done
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'creator_id', 'status']);
            $t->index(['tenant_id', 'assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_tasks');
        Schema::dropIfExists('creator_notes');
        Schema::table('creators', function (Blueprint $t) {
            $t->dropForeign(['relationship_owner_id']);
            $t->dropColumn([
                'relationship_stage', 'relationship_health', 'relationship_owner_id', 'source',
                'preferred_channel', 'manager_name', 'manager_contact', 'do_not_contact',
                'consent_at', 'first_contact_at', 'last_interaction_at', 'next_follow_up_at',
            ]);
        });
    }
};
