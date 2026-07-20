<?php
namespace App\Domain\Identity\Models;
use Illuminate\Database\Eloquent\Model;
class Permission extends Model {
    protected $fillable = ['key','label'];
}
