<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintingProductType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'icon', 'is_active', 'allow_custom_name', 'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_custom_name' => 'boolean',
    ];

    public function printingTypes()
    {
        return $this->hasMany(PrintingType::class);
    }
}
