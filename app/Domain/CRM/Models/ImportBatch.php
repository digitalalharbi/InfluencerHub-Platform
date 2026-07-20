<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ImportBatch extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','type','source_file','status','imported_count','skipped_count','created_at','rolled_back_at'];
    protected $casts = ['created_at' => 'datetime', 'rolled_back_at' => 'datetime'];
    public function clients(): HasMany { return $this->hasMany(Client::class); }
}
