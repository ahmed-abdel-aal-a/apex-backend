<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // In a real application, you would get the current store ID from Auth, Session, or Request.
        // For example:
        // if (auth()->check() && auth()->user()->store_id) {
        //     $builder->where('store_id', auth()->user()->store_id);
        // }
        // We will add a placeholder that can be extended.
        
        $storeId = config('tenant.current_store_id') ?? request()->header('X-Store-ID') ?? 1;
        
        if ($storeId) {
            $builder->where($model->getTable() . '.store_id', $storeId);
        }
    }
}
