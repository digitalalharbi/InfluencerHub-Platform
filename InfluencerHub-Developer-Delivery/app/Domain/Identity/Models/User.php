<?php
namespace App\Domain\Identity\Models;

use App\Domain\Tenancy\Models\{OrganizationMembership, Organization, Workspace};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** الـFactory خارج مسار الاسم القياسي (النموذج داخل Domain). */
    protected static function newFactory(): \Database\Factories\UserFactory
    {
        return \Database\Factories\UserFactory::new();
    }

    protected $fillable = ['name','email','phone','password','is_active'];
    protected $hidden = ['password','remember_token','two_factor_secret'];
    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'is_system_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany { return $this->hasMany(OrganizationMembership::class); }

    /** دور المستخدم داخل مؤسسة/workspace (أو سياق المستأجر الحالي). */
    /**
     * دور المستخدم في مؤسسة — يُحفظ داخل الطلب الواحد.
     *
     * تُستدعى في كل فحص صلاحية (سياسات + قائمة + صفحة)، فكانت تُصدر
     * استعلامًا مستقلًا في كل مرة: 9 استعلامات متطابقة في لوحة التحكم وحدها.
     * الذاكرة على مستوى النسخة فقط، فلا تتسرّب بين الطلبات ولا بين المستخدمين.
     *
     * @var array<string,?string>
     */
    private array $roleCache = [];

    public function roleIn(int $organizationId, ?int $workspaceId = null): ?string
    {
        $key = $organizationId . ':' . ($workspaceId ?? '-');

        return $this->roleCache[$key] ??= $this->memberships()
            ->where('organization_id', $organizationId)
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where('status', 'active')
            ->value('role');
    }

    /** تُستدعى بعد تغيير العضويات حتى لا يبقى دور قديم في الذاكرة. */
    public function forgetRoleCache(): void
    {
        $this->roleCache = [];
    }

    public function hasRoleIn(int $organizationId, array $roles, ?int $workspaceId = null): bool
    {
        return in_array($this->roleIn($organizationId, $workspaceId), $roles, true);
    }
}
