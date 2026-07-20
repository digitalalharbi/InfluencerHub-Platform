<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class Brand extends Model {
    use BelongsToTenant, SoftDeletes;
    protected $fillable = ['tenant_id','client_id','name','slug','normalized_name','logo_path','sector','website','email_domain','website_domain','commercial_registration','description','tone_of_voice','target_audience','brand_guidelines_path','status','created_by','updated_by','preferred_language','prohibited_topics','required_messages','visual_guidelines','contact_information','current_version','submitted_at','reviewed_at','reviewed_by','changes_reason'];
    protected $casts = ['prohibited_topics'=>'array','required_messages'=>'array','contact_information'=>'array','submitted_at'=>'datetime','reviewed_at'=>'datetime','current_version'=>'integer'];
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function statusHistory(): HasMany { return $this->hasMany(BrandStatusHistory::class); }
    public function decisions(): HasMany { return $this->hasMany(BrandReviewDecision::class); }
    public function versions(): HasMany { return $this->hasMany(BrandVersion::class); }
    public function socialAccounts(): HasMany { return $this->hasMany(BrandSocialAccount::class); }

    /**
     * علاقات مساحات العمل بهذه العلامة (المالك، الوكالات المفوَّضة، …).
     *
     * **بلا تنطيق بالمستأجر**: الصفّ يربط مستأجرَين — مستأجر العلامة ومستأجر
     * الوكالة — فأيّ تنطيق يُخفي أحد الطرفين عن الآخر. الحراسة مسؤولية
     * المستدعي، وقد تُرك ذلك صريحًا في `BrandWorkspaceRelationship` نفسه.
     */
    public function workspaceRelationships(): HasMany
    {
        return $this->hasMany(BrandWorkspaceRelationship::class);
    }

    /** مالك العلامة — علاقة واحدة حيّة على الأكثر. */
    public function ownerRelationship(): ?BrandWorkspaceRelationship
    {
        return $this->workspaceRelationships()
            ->where('relationship_type', BrandWorkspaceRelationship::OWNER)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->first();
    }

    /** العلامة تملك نفسها متى وُجد لها صفّ مالك حيّ. */
    public function isSelfOwned(): bool { return $this->ownerRelationship() !== null; }
    /** الحقول قابلة للتعديل من العميل (draft/changes_requested فقط). */
    public function isEditableByClient(): bool { return in_array($this->status, ['draft','changes_requested'], true); }
    public const STATUSES = ['draft','submitted','under_review','approved','changes_requested','suspended','archived'];
}
