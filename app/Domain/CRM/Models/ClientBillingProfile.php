<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ClientBillingProfile extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','client_id','billing_name','billing_email','billing_contact_name','billing_contact_phone','tax_number','vat_registered','billing_address','purchase_order_required','default_currency','invoice_notes','payment_terms_days'];
    protected $casts = ['vat_registered' => 'boolean', 'purchase_order_required' => 'boolean', 'payment_terms_days' => 'integer'];
}
