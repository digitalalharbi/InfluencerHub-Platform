<?php
namespace App\Domain\Creators\Models;
use Illuminate\Database\Eloquent\Model;
/** غير مقيّد بالمستأجر (يُسجَّل قبل معرفة المستأجر). */
class CreatorApplicationAccessAttempt extends Model {
    public $timestamps = false;
    protected $fillable = ['reference','outcome','ip','user_agent','created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
