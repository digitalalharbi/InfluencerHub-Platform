<?php
namespace App\Domain\Billing\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionItem extends Model {
    protected $fillable = ['subscription_id','plan_price_id','quantity'];
    protected $casts = ['quantity'=>'integer'];
}
