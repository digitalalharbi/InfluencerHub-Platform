<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ClientAddress extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','client_id','type','label','recipient_name','phone','country_code','region','city','district','street','building_number','postal_code','additional_number','latitude','longitude','is_default','created_by','updated_by','archived_at'];
    protected $casts = ['is_default' => 'boolean', 'archived_at' => 'datetime', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    public const TYPES = ['headquarters','billing','shipping','branch','other'];
}
