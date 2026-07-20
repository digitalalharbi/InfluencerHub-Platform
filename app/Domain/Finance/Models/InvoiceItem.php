<?php

namespace App\Domain\Finance\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'invoice_id', 'description', 'quantity',
        'unit_price_minor', 'line_total_minor', 'deliverable_id', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer', 'unit_price_minor' => 'integer', 'line_total_minor' => 'integer',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
