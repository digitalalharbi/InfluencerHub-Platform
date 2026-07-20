<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CreatorApplicationPlatform extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','application_id','platform','username','profile_url','followers_count','average_views','engagement_rate','is_verified','verification_method','source','status','last_verified_at'];
}
