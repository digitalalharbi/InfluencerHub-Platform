<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * حدث ويبهوك مُخزَّن — مُعرَّف بمعرّف المزوّد لمنع التكرار، مع صحّة التوقيع وحالته.
 * لا يستعمل TenantContext (يصل بلا سياق مستأجر) — يُحلّ المستأجر عند المعالجة.
 */
class IntegrationWebhookEvent extends Model
{
    protected $fillable = ['tenant_id', 'provider', 'event_id', 'type', 'signature_valid', 'status', 'payload', 'error', 'received_at', 'processed_at'];

    protected $casts = [
        'signature_valid' => 'boolean',
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public $timestamps = false;
}
