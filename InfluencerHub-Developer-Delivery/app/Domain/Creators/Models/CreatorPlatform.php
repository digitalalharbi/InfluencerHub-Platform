<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CreatorPlatform extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','creator_id','platform','handle','url','followers_count'];
    protected $casts = ['followers_count' => 'integer'];
}
