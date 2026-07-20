<?php
namespace App\Domain\Billing\Models;
use Illuminate\Database\Eloquent\Model;
class Coupon extends Model {
    protected $fillable = ['code','type','value','currency','max_redemptions','redeemed_count','expires_at','is_active'];
    protected $casts = ['value'=>'integer','expires_at'=>'datetime','is_active'=>'boolean'];
}
