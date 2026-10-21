<?php

namespace App\Observers;

use App\Models\PrintingType;
use App\Models\Offer;
use App\Jobs\RevalidateStorefrontOffersJob;

class PrintingTypeObserver
{
    /**
     * Handle the PrintingType "created" event.
     */
    public function created(PrintingType $printingType): void
    {
        //
    }

    /**
     * Handle the PrintingType "updated" event.
     */
    public function updated(PrintingType $printingType): void
    {
        if ($printingType->wasChanged(['is_active'])) {
            $this->checkAndRevalidate($printingType);
        }
    }

    /**
     * Handle the PrintingType "deleted" event.
     */
    public function deleted(PrintingType $printingType): void
    {
        $this->checkAndRevalidate($printingType);
    }

    /**
     * Handle the PrintingType "restored" event.
     */
    public function restored(PrintingType $printingType): void
    {
        $this->checkAndRevalidate($printingType);
    }

    /**
     * Handle the PrintingType "force deleted" event.
     */
    public function forceDeleted(PrintingType $printingType): void
    {
        $this->checkAndRevalidate($printingType);
    }

    private function checkAndRevalidate(PrintingType $printingType): void
    {
        $isLinked = Offer::where('linkable_type', 'printing_type')->where('linkable_id', $printingType->id)->exists();
        if ($isLinked) {
            RevalidateStorefrontOffersJob::dispatch();
        }
    }
}
