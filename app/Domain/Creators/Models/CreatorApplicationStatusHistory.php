<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CreatorApplicationStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'creator_application_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','application_id','from_status','to_status','actor_id','reason','internal_notes','applicant_message','request_id','occurred_at'];
    protected $casts = ['expires_at' => 'datetime', 'verified_at' => 'datetime', 'occurred_at' => 'datetime', 'created_at' => 'datetime'];
}
