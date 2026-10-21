<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\Offer;
use App\Jobs\RevalidateStorefrontOffersJob;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        // No need to invalidate on create, as it cannot be linked to an offer yet.
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        if ($product->wasChanged(['is_active', 'quantity'])) {
            $this->checkAndRevalidate($product);
        }
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->checkAndRevalidate($product);
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        $this->checkAndRevalidate($product);
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        $this->checkAndRevalidate($product);
    }

    private function checkAndRevalidate(Product $product): void
    {
        $isLinked = Offer::where('linkable_type', 'product')->where('linkable_id', $product->id)->exists();
        if ($isLinked) {
            RevalidateStorefrontOffersJob::dispatch();
        }
    }
}
