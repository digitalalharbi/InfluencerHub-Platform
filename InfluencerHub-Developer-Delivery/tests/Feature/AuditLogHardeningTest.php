<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Phase 3 — تصلّب سجل التدقيق: append-only على مستوى التطبيق + Trigger على مستوى PostgreSQL. */
class AuditLogHardeningTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    public function test_logger_records_new_hardened_fields(): void
    {
        $log = AuditLogger::log('test.event', null, ['k' => 'v'], null, null, ['old' => ['a' => 1], 'new' => ['a' => 2]]);
        $this->assertNotNull($log->occurred_at);
        $this->assertEquals(['a' => 1], $log->fresh()->old_values);
        $this->assertEquals(['a' => 2], $log->fresh()->new_values);
    }

    public function test_model_blocks_update_at_application_level(): void
    {
        $log = AuditLogger::log('test.event');
        $this->expectException(\RuntimeException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_model_blocks_delete_at_application_level(): void
    {
        $log = AuditLogger::log('test.event');
        $this->expectException(\RuntimeException::class);
        $log->delete();
    }

    /** المناعة الحقيقية: حتى تجاوز الـEloquent بـUPDATE خام يفشل عبر Trigger في PostgreSQL. */
    public function test_database_trigger_blocks_raw_update(): void
    {
        $log = AuditLogger::log('test.event');
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);
    }

    public function test_database_trigger_blocks_raw_delete(): void
    {
        $log = AuditLogger::log('test.event');
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->delete();
    }

    public function test_triggers_actually_exist_in_schema(): void
    {
        $triggers = DB::select("SELECT tgname FROM pg_trigger WHERE tgrelid = 'audit_logs'::regclass AND NOT tgisinternal");
        $names = array_map(fn ($r) => $r->tgname, $triggers);
        $this->assertContains('trg_audit_logs_no_update', $names);
        $this->assertContains('trg_audit_logs_no_delete', $names);
    }
}
