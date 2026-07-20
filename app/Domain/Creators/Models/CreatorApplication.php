<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class CreatorApplication extends Model {
    use BelongsToTenant, SoftDeletes;
    protected $fillable = ['tenant_id','reference','status','account_type','capabilities','full_name','professional_name','email','phone',
        'whatsapp','country_code','city','gender','languages','bio','avatar_path','categories','current_step',
        'email_verified_at','phone_verified_at','terms_accepted_at','privacy_accepted_at','assigned_reviewer_id',
        'submitted_at','reviewed_at','rejection_reason','creator_id','user_id','mowthooq_license_number',
        'mowthooq_issued_at','mowthooq_expires_at','mowthooq_document_path','mowthooq_status','mowthooq_rejection_reason',
        'beneficiary_name','bank_name','iban_encrypted','iban_last4','iban_document_path','tax_number','financial_verification_status','access_token_hash','access_token_expires_at','access_token_revoked_at','workspace_slug','tenant_resolution_source'];
    protected $casts = ['languages' => 'array', 'categories' => 'array', 'capabilities' => 'array', 'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime', 'terms_accepted_at' => 'datetime', 'privacy_accepted_at' => 'datetime',
        'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'mowthooq_issued_at' => 'date', 'mowthooq_expires_at' => 'date', 'access_token_expires_at' => 'datetime', 'access_token_revoked_at' => 'datetime'];
    protected $hidden = ['iban_encrypted','access_token_hash'];

    /** الحالات القابلة للتحرير من قِبل المتقدّم (مسودة). */
    public const EDITABLE_BY_APPLICANT = ['draft','email_verification_pending','phone_verification_pending','completion_required'];
    public function platforms(): HasMany { return $this->hasMany(CreatorApplicationPlatform::class, 'application_id'); }
    public function services(): HasMany { return $this->hasMany(CreatorApplicationService::class, 'application_id'); }
    public function portfolios(): HasMany { return $this->hasMany(CreatorApplicationPortfolio::class, 'application_id'); }
    public function documents(): HasMany { return $this->hasMany(CreatorApplicationDocument::class, 'application_id'); }
    public function messages(): HasMany { return $this->hasMany(CreatorApplicationMessage::class, 'application_id'); }
    /** مراجعات الطلب (قرارات وملاحظات داخلية). */
    public function reviews(): HasMany { return $this->hasMany(CreatorApplicationReview::class, 'application_id'); }
    public function statusHistory(): HasMany { return $this->hasMany(CreatorApplicationStatusHistory::class, 'application_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(\App\Domain\Identity\Models\User::class, 'assigned_reviewer_id'); }
    public function isEditableByApplicant(): bool { return in_array($this->status, self::EDITABLE_BY_APPLICANT, true); }
}
