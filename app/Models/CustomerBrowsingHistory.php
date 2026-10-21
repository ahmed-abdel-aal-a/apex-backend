<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasTenant;

class CustomerBrowsingHistory extends Model
{
    use HasFactory, HasUuids, HasTenant;

    protected $fillable = [
        'store_id',
        'customer_id',
        'path',
        'title',
        'type',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
