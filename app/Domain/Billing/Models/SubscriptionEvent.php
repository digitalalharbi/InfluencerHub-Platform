<?php
namespace App\Domain\Billing\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionEvent extends Model {
    protected $fillable = ['subscription_id','type','data','occurred_at'];
    protected $casts = ['data'=>'array','occurred_at'=>'datetime'];
}
