<?php
namespace App\Domain\Tenancy\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
/** مورد عيّنة تابع للمستأجر — لإثبات العزل عبر HTTP وQueue وCache. */
class Note extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','workspace_id','user_id','body'];
}
