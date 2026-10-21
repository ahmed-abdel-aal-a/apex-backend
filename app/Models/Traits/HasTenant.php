<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;

trait HasTenant
{
    /**
     * Boot the trait for a model.
     *
     * @return void
     */
    public static function bootHasTenant()
    {
        // Forcefully inject store_id on creation to prevent spoofing
        static::creating(function ($model) {
            $model->store_id = config('tenant.current_store_id', 1);
        });

        // Apply global scope
        static::addGlobalScope(new TenantScope);
    }
}
