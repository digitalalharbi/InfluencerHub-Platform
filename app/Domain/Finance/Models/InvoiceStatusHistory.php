<?php

namespace App\Domain\Finance\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceStatusHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'invoice_status_history';

    protected $fillable = ['tenant_id', 'invoice_id', 'from_status', 'to_status', 'actor_id', 'reason', 'occurred_at'];

    protected $casts = ['occurred_at' => 'datetime'];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
