<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ClientContact extends Model {
    use BelongsToTenant, SoftDeletes;
    protected $fillable = ['tenant_id','client_id','name','job_title','department','email','phone','whatsapp','is_primary','preferred_channel','notes'];
    protected $casts = ['is_primary'=>'boolean'];
}
