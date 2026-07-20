<?php
namespace App\Domain\Billing\Models;
use Illuminate\Database\Eloquent\Model;
class AddOn extends Model {
    protected $fillable = ['key','label','feature_key','grant_value','grant_boolean'];
    protected $casts = ['grant_value'=>'integer','grant_boolean'=>'boolean'];
}
