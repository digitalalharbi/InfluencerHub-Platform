<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->json('old_values')->nullable()->after('changes');
            $t->json('new_values')->nullable()->after('old_values');
            $t->string('user_agent')->nullable()->after('ip');
            $t->uuid('request_id')->nullable()->after('user_agent');
            $t->timestamp('occurred_at')->nullable()->after('request_id');
            $t->index(['tenant_id', 'occurred_at']);
        });
        // occurred_at للسجلات القائمة = created_at
        DB::statement('UPDATE audit_logs SET occurred_at = created_at WHERE occurred_at IS NULL');

        // مناعة فعلية على مستوى قاعدة البيانات (PostgreSQL): منع UPDATE/DELETE بـTrigger.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_logs_block_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs is append-only: % is not permitted', TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS trg_audit_logs_no_update ON audit_logs;
                CREATE TRIGGER trg_audit_logs_no_update BEFORE UPDATE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION audit_logs_block_mutation();

                DROP TRIGGER IF EXISTS trg_audit_logs_no_delete ON audit_logs;
                CREATE TRIGGER trg_audit_logs_no_delete BEFORE DELETE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION audit_logs_block_mutation();
            SQL);
        }
    }
    public function down(): void {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_logs_no_update ON audit_logs;
                            DROP TRIGGER IF EXISTS trg_audit_logs_no_delete ON audit_logs;
                            DROP FUNCTION IF EXISTS audit_logs_block_mutation();');
        }
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropColumn(['old_values', 'new_values', 'user_agent', 'request_id', 'occurred_at']);
        });
    }
};
