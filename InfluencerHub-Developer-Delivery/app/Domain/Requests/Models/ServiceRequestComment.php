<?php
namespace App\Domain\Requests\Models;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ServiceRequestComment extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','service_request_id','author_id','author_type','body','is_internal','created_at'];
    protected $casts = ['is_internal'=>'boolean','created_at'=>'datetime'];
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
