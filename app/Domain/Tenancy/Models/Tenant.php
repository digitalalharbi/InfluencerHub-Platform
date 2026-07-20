<?php
namespace App\Domain\Tenancy\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Tenant extends Model {
    use SoftDeletes;
    /** `type` نوع المستأجر (agency|brand|platform_admin) — محور مستقلّ عن `deployment_mode` (كيف يُستضاف). */
    protected $fillable = ['name','slug','type','deployment_mode','status','settings'];

    public const TYPE_AGENCY = 'agency';
    public const TYPE_BRAND = 'brand';
    public const TYPE_PLATFORM_ADMIN = 'platform_admin';
    public const TYPES = [self::TYPE_AGENCY, self::TYPE_BRAND, self::TYPE_PLATFORM_ADMIN];

    public function isBrandWorkspace(): bool { return $this->type === self::TYPE_BRAND; }
    public function isAgency(): bool { return $this->type === self::TYPE_AGENCY; }
    protected $casts = ['settings' => 'array'];
    public function organizations(): HasMany { return $this->hasMany(Organization::class); }
    public function isActive(): bool { return $this->status === 'active'; }
}
