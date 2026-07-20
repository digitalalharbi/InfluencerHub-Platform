<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CreatorApplicationMessage extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','application_id','sender_type','sender_id','body','created_at'];
    protected $casts = ['expires_at' => 'datetime', 'verified_at' => 'datetime', 'occurred_at' => 'datetime', 'created_at' => 'datetime'];
}
