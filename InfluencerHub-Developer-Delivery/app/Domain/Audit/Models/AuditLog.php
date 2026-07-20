<?php
namespace App\Domain\Audit\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model {
    public $timestamps = false;
    protected $fillable = ['tenant_id','user_id','actor_name','action','auditable_type','auditable_id','changes','old_values','new_values','ip','user_agent','request_id','occurred_at','created_at'];
    protected $casts = ['changes'=>'array','old_values'=>'array','new_values'=>'array','created_at'=>'datetime','occurred_at'=>'datetime'];
    // append-only على مستوى التطبيق. المناعة الفعلية مطبّقة أيضًا على مستوى PostgreSQL
    // عبر Trigger (trg_audit_logs_no_update/no_delete) في هجرة 2026_07_16_440001.
    protected static function booted(): void {
        static::updating(fn () => throw new \RuntimeException('سجل التدقيق للقراءة فقط (append-only).'));
        static::deleting(fn () => throw new \RuntimeException('سجل التدقيق للقراءة فقط (append-only).'));
    }
}
