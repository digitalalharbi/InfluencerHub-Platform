<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CreatorApplicationDocument extends Model {
    use BelongsToTenant, SoftDeletes;
    protected $fillable = ['tenant_id','application_id','kind','disk','path','original_name','stored_name','mime','extension','size_bytes','checksum_sha256','uploaded_by','status','transfer_status','transferred_path','transfer_idempotency_key','transferred_at'];
    protected $casts = ['size_bytes' => 'integer'];
    public function versions(): HasMany { return $this->hasMany(CreatorApplicationDocumentVersion::class, 'document_id'); }
}
