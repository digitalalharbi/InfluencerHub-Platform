<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CreatorPortfolio extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','creator_id','type','url','path','category','previous_brand','description','status','sort_order'];
}
