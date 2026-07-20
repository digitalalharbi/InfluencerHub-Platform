<?php
namespace App\Domain\Automation\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class AutomationLog extends Model {
    use BelongsToTenant;
    protected $table = 'automation_log';
    public $timestamps = false;
    protected $fillable = ['tenant_id','rule','subject_type','subject_id','detail','created_at'];
    protected $casts = ['created_at'=>'datetime'];
}
