<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ClientDocumentAccessLog extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','document_id','user_id','actor_type','action','ip','user_agent','created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
