<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ClientMemberStatusHistory extends Model {
    use BelongsToTenant; public $timestamps=false;
    protected $table='client_member_status_history';
    protected $fillable=['tenant_id','client_member_id','from_status','to_status','changed_by','created_at'];
    protected $casts=['created_at'=>'datetime'];
    protected static function booted(): void {
        static::updating(fn()=>throw new \RuntimeException('append-only'));
        static::deleting(fn()=>throw new \RuntimeException('append-only'));
    }
}
