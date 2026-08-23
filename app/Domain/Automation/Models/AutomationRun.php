<?php

namespace App\Domain\Automation\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** تشغيلة أتمتة — سجلّ ما طابق ونُفِّذ (أو فشل) بسياقه ونتيجته. */
class AutomationRun extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'rule_id', 'trigger', 'status', 'context', 'result', 'error', 'created_at'];

    protected $casts = ['context' => 'array', 'result' => 'array', 'created_at' => 'datetime'];
}
