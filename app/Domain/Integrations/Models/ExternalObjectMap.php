<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** خريطة كائن خارجي↔محلي — تمنع التكرار وتربط المزامنة بالسجلّ المحلي. */
class ExternalObjectMap extends Model
{
    use BelongsToTenant;

    protected $table = 'external_object_map';

    protected $fillable = ['tenant_id', 'provider', 'external_type', 'external_id', 'local_type', 'local_id', 'synced_at'];

    protected $casts = ['synced_at' => 'datetime', 'local_id' => 'integer'];
}
