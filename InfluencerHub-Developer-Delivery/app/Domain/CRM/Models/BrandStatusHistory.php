<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class BrandStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'brand_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','brand_id','from_status','to_status','actor_id','reason','request_id','occurred_at'];
    protected $casts = ['occurred_at' => 'datetime'];
}
