<?php

namespace App\Observers;

use App\Models\Offer;
use App\Jobs\RevalidateStorefrontOffersJob;

class OfferObserver
{
    /**
     * Handle the Offer "created" event.
     */
    public function created(Offer $offer): void
    {
        RevalidateStorefrontOffersJob::dispatch();
    }

    /**
     * Handle the Offer "updated" event.
     */
    public function updated(Offer $offer): void
    {
        RevalidateStorefrontOffersJob::dispatch();
    }

    /**
     * Handle the Offer "deleted" event.
     */
    public function deleted(Offer $offer): void
    {
        RevalidateStorefrontOffersJob::dispatch();
    }

    /**
     * Handle the Offer "restored" event.
     */
    public function restored(Offer $offer): void
    {
        RevalidateStorefrontOffersJob::dispatch();
    }

    /**
     * Handle the Offer "force deleted" event.
     */
    public function forceDeleted(Offer $offer): void
    {
        RevalidateStorefrontOffersJob::dispatch();
    }
}
