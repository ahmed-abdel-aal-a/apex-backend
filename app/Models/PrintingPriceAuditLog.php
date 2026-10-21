<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintingPriceAuditLog extends Model
{
    protected $table = 'printing_price_audit_log';
    
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'field_name',
        'old_value',
        'new_value',
        'changed_by',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }
}
