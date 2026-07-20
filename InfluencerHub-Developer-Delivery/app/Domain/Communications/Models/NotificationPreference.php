<?php
namespace App\Domain\Communications\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class NotificationPreference extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','user_id','category','in_app','email','sms'];
    protected $casts = ['in_app'=>'boolean','email'=>'boolean','sms'=>'boolean'];
    /** الفئات القابلة للضبط (تظهر في الإعدادات). */
    public const CATEGORIES = ['brands'=>'العلامات','documents'=>'المستندات','profile'=>'الملف القانوني','team'=>'الفريق','billing'=>'الفوترة','general'=>'عام'];
}
