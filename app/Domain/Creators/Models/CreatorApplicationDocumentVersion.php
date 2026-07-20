<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CreatorApplicationDocumentVersion extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','document_id','version','path','checksum_sha256','size_bytes','uploaded_by','created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
