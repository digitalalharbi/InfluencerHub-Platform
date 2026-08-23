<?php

namespace App\Domain\Automation\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** قاعدة أتمتة: محفّز + شروط + إجراءات. */
class AutomationRule extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'name', 'trigger', 'conditions', 'actions', 'enabled', 'priority', 'is_system'];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'enabled' => 'boolean',
        'is_system' => 'boolean',
        'priority' => 'integer',
    ];
}
