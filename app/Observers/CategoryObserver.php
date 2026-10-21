<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Offer;
use App\Jobs\RevalidateStorefrontOffersJob;

class CategoryObserver
{
    /**
     * Handle the Category "created" event.
     */
    public function created(Category $category): void
    {
        //
    }

    /**
     * Handle the Category "updated" event.
     */
    public function updated(Category $category): void
    {
        if ($category->wasChanged(['is_active'])) {
            $this->checkAndRevalidate($category);
        }
    }

    /**
     * Handle the Category "deleted" event.
     */
    public function deleted(Category $category): void
    {
        $this->checkAndRevalidate($category);
    }

    /**
     * Handle the Category "restored" event.
     */
    public function restored(Category $category): void
    {
        $this->checkAndRevalidate($category);
    }

    /**
     * Handle the Category "force deleted" event.
     */
    public function forceDeleted(Category $category): void
    {
        $this->checkAndRevalidate($category);
    }

    private function checkAndRevalidate(Category $category): void
    {
        $isLinked = Offer::where('linkable_type', 'category')->where('linkable_id', $category->id)->exists();
        if ($isLinked) {
            RevalidateStorefrontOffersJob::dispatch();
        }
    }
}
