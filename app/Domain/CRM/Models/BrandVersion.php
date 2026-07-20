<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class BrandVersion extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','brand_id','version','snapshot','created_by','created_at'];
    protected $casts = ['created_at' => 'datetime', 'snapshot' => 'array'];
}
