<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAddon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'addon_group_id',
        'name',
        'description',
        'type',
        'price',
        'price_type',
        'printing_type_id',
        'requires_upload',
        'allow_multiple_uploads',
        'max_uploads',
        'track_inventory',
        'quantity',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'price' => 'decimal:2',
        'requires_upload' => 'boolean',
        'allow_multiple_uploads' => 'boolean',
        'max_uploads' => 'integer',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AddonGroup::class, 'addon_group_id');
    }

    public function getAvailableQuantityAttribute()
    {
        if (!$this->track_inventory) return 999999;
        
        $reserved = \App\Models\StockReservation::where('product_addon_id', $this->id)
            ->where('expires_at', '>', now())
            ->sum('quantity');

        return max(0, $this->quantity - $reserved);
    }

    public function printingType(): BelongsTo
    {
        return $this->belongsTo(PrintingType::class);
    }
}
