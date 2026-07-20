<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ClientNote extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','client_id','author_id','body'];
}
