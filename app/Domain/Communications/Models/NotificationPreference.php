<?php
namespace App\Domain\Communications\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class NotificationPreference extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','user_id','category','in_app','email','whatsapp','sms'];
    protected $casts = ['in_app'=>'boolean','email'=>'boolean','whatsapp'=>'boolean','sms'=>'boolean'];
    // الفئات القابلة للضبط أصبحت من المصدر القانونيّ الوحيد:
    // {@see \App\Domain\Communications\Enums\NotificationCategory} (map()/values()).
}
