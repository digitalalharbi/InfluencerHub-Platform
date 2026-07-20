<?php
namespace App\Domain\CRM\Models;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class ClientDocument extends Model {
    use BelongsToTenant, SoftDeletes;
    protected $fillable = ['tenant_id','client_id','category','visibility','title','disk','path','original_name','stored_name','mime','extension','size_bytes','checksum_sha256','version_number','status','uploaded_by','reviewed_by','reviewed_at','rejection_reason','expires_at'];
    protected $casts = ['size_bytes' => 'integer', 'version_number' => 'integer', 'reviewed_at' => 'datetime', 'expires_at' => 'datetime'];
    /** فئات: وكالة (Phase 3) + بوابة العميل (Phase 5) — اتحاد للتوافق. */
    public const CATEGORIES = ['contract','cr','vat','brief','report','invoice',
        'commercial_registration','tax_certificate','brand_guidelines','purchase_order','bank_document','identity_document','billing_document','other'];
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function versions(): HasMany { return $this->hasMany(ClientDocumentVersion::class, 'document_id'); }
    public function reviews(): HasMany { return $this->hasMany(ClientDocumentReview::class, 'document_id'); }
}
