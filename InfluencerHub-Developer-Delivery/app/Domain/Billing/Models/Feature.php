<?php
namespace App\Domain\Billing\Models;
use Illuminate\Database\Eloquent\Model;
class Feature extends Model {
    protected $fillable = ['key','label','type'];
}
