<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CreatorApplicationDocumentAccessLog extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','document_id','user_id','action','ip','user_agent','created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
