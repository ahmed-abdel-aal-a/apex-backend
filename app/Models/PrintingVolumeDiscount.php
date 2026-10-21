<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintingVolumeDiscount extends Model
{
    protected $fillable = [
        'printing_type_id',
        'min_qty',
        'max_qty',
        'discount_basis_points',
        'added_production_days',
    ];

    public function printingType()
    {
        return $this->belongsTo(PrintingType::class);
    }
}
