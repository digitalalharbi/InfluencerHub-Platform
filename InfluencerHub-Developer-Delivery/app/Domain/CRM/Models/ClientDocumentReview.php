<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ClientDocumentReview extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','document_id','reviewer_id','decision','note','created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
