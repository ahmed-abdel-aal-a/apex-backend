<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintingAttributeValue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'printing_attribute_id',
        'value',
        'label',
        'fixed_price',
        'unit_price',
        'multiplier_price',
        'weight',
        'weight_type',
        'tiered_prices',
        'added_production_days',
        'required_upload_category',
        'sort_order',
    ];

    protected $casts = [
        'tiered_prices' => 'array',
    ];

    public function attribute()
    {
        return $this->belongsTo(PrintingAttribute::class, 'printing_attribute_id');
    }
}
