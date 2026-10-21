<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintingType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'printing_product_type_id',
        'name',
        'description',
        'image',
        'pricing_method',
        'base_price_fixed',
        'base_price_unit',
        'base_price_multiplier',
        'min_quantity',
        'quantity_step',
        'min_chargeable_area',
        'min_chargeable_price',
        'is_active',
        'requires_upload',
        'upload_rules',
        'upload_categories',
        'standard_roll_sizes',
        'max_print_width',
        'max_print_length',
        'bleed_margin',
        'safe_zone',
        'thickness_factor',
        'base_production_days',
        'is_market_sensitive',
        'market_notice',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_market_sensitive' => 'boolean',
        'requires_upload' => 'boolean',
        'upload_categories' => 'array',
        'standard_roll_sizes' => 'array',
        'upload_rules' => 'array',
    ];

    public function productType()
    {
        return $this->belongsTo(PrintingProductType::class, 'printing_product_type_id');
    }

    public function attributes()
    {
        return $this->hasMany(PrintingAttribute::class);
    }

    public function volumeDiscounts()
    {
        return $this->hasMany(PrintingVolumeDiscount::class);
    }
}
