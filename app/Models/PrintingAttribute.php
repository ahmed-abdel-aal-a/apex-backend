<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintingAttribute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'printing_type_id',
        'name',
        'label',
        'type',
        'step',
        'is_required',
        'sort_order',
        'min_value',
        'max_value',
        'conditional_rules',
        'linked_upload_category',
        'is_dimension_width',
        'is_dimension_length',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'conditional_rules' => 'array',
        'is_dimension_width' => 'boolean',
        'is_dimension_length' => 'boolean',
    ];

    public function printingType()
    {
        return $this->belongsTo(PrintingType::class);
    }

    public function values()
    {
        return $this->hasMany(PrintingAttributeValue::class, 'printing_attribute_id');
    }
}
