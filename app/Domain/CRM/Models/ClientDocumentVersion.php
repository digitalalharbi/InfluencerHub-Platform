<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ClientDocumentVersion extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','document_id','version_number','path','checksum_sha256','size_bytes','uploaded_by','created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
