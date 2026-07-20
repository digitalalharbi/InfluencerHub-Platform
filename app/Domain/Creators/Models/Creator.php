<?php
namespace App\Domain\Creators\Models;
use App\Domain\Creators\Enums\CreatorStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Creator extends Model {
    use BelongsToTenant, SoftDeletes;
    protected $fillable = ['tenant_id','creator_number','type','display_name','handle','email','phone','city',
        'country_code','primary_platform','followers_count','content_categories','status','rate_per_post_minor','bio','created_by','user_id','professional_name','whatsapp','gender','languages','mowthooq_license_number','mowthooq_expires_at','mowthooq_status','beneficiary_name','bank_name','iban_encrypted','iban_last4','financial_verification_status','avatar_path','mowthooq_document_path','iban_document_path','publisher_id'];
    protected $casts = ['content_categories' => 'array', 'languages' => 'array', 'followers_count' => 'integer', 'rate_per_post_minor' => 'integer', 'mowthooq_expires_at' => 'date'];
    protected $hidden = ['iban_encrypted'];
    public function platforms(): HasMany { return $this->hasMany(CreatorPlatform::class); }
    public function services(): HasMany { return $this->hasMany(CreatorService::class); }
    public function portfolios(): HasMany { return $this->hasMany(CreatorPortfolio::class); }
    public function capabilities(): HasMany { return $this->hasMany(CreatorCapability::class); }

    /**
     * هل يملك الصانع هذه القدرة؟
     * يقرأ من القدرات المطبَّعة، ويرجع إلى العمود القديم `type` إن لم تُنقل بعد،
     * فلا ينكسر صانع أُنشئ قبل الهجرة.
     */
    public function hasCapability(string $key): bool
    {
        return in_array($key, $this->capabilityKeys(), true);
    }

    /**
     * @return array<int,string> مفاتيح القدرات المفعّلة
     *
     * غياب الصفوف تمامًا يعني «لم يُنقل بعد» فيُقرأ العمود القديم. أما وجود صفّ
     * معطَّل فهو نفي صريح للقدرة، ولا يجوز أن يُبطله النوع القديم — وإلا لتعذّر
     * على الصانع إلغاء قدرة أبدًا.
     */
    public function capabilityKeys(): array
    {
        if ($this->capabilities->isEmpty()) {
            return \App\Domain\Creators\Services\CreatorCapabilityService::LEGACY_TO_CAPS[$this->type] ?? [];
        }

        return $this->capabilities->where('is_enabled', true)->pluck('capability')->values()->all();
    }
    public function isActive(): bool { return $this->status === CreatorStatus::Active->value; }
}
