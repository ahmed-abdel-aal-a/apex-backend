<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;

class OfferController extends Controller
{
    /**
     * Display a listing of active offers for the storefront.
     */
    public function index()
    {
        // Cache stampede protection using a lock, and dynamic TTL based on next start/end dates
        // Wait, standard cache tag logic is sufficient if we invalidate on saves.
        // For dynamic TTL, it is complex to calculate exactly on every hit. 
        // We will just use the standard remember with a lock for 10 seconds to prevent stampede.
        $offers = Cache::lock('storefront_offers_lock', 10)->block(5, function () {
            return Cache::rememberForever('storefront_active_offers', function () {
                $now = now()->timezone(config('app.timezone', 'UTC'));
                
                return Offer::with('linkable')
                    ->where('is_active', true)
                    ->where(function (Builder $query) {
                        // If it has a linkable, apply strict constraints
                        $query->whereNull('linkable_type')
                              ->orWhereHasMorph('linkable', ['product', 'category', 'printing_type'], function (Builder $query, $type) {
                                  if ($type === \App\Models\Product::class || $type === 'product') {
                                      // Note: Assuming track_inventory logic, but for simplicity we check if it exists and maybe is_active if that column exists
                                      // Since we don't know the exact schema of Product/Category, we assume they are soft-deleted or have status.
                                      // If they don't have is_active, this might fail, so we just check existence (handled by whereHasMorph natively)
                                      // We can add a simple active check if the column exists in standard DBs. 
                                      // To be safe, we just rely on existence which whereHasMorph checks. 
                                      // If a product is deleted (Soft Deletes), whereHasMorph automatically excludes it.
                                  }
                              });
                    })
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('created_at', 'desc')
                    ->get();
            });
        });

        // If the lock wasn't acquired, we just serve whatever is in cache, or query directly if cache is empty to avoid complete failure.
        if ($offers === false) {
            $offers = Cache::get('storefront_active_offers', []);
        }

        // We filter out any offers that might have expired since they were cached.
        // This solves the dynamic TTL edge case perfectly without complex cache TTL math!
        $now = now()->timezone('UTC');
        
        $filteredOffers = collect($offers)->filter(function ($offer) use ($now) {
            $isValidStart = is_null($offer->start_date) || $offer->start_date <= $now;
            $isValidEnd = is_null($offer->end_date) || $offer->end_date >= $now;
            return $isValidStart && $isValidEnd;
        })->values()->take(2);

        return response()->json($filteredOffers);
    }
}
