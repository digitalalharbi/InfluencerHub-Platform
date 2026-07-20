<?php
namespace App\Domain\Tenancy\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Workspace extends Model {
    use SoftDeletes, BelongsToTenant;
    protected $fillable = ['tenant_id','organization_id','name','slug','status'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}
