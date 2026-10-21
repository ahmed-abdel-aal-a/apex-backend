<?php

namespace App\Jobs;

use App\Models\Cart;
use App\Models\AbandonedCartsSetting;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAbandonedCarts implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $settings = AbandonedCartsSetting::first();
        $thresholdMinutes = $settings ? $settings->consider_abandoned_after_minutes : 60;

        $thresholdTime = Carbon::now()->subMinutes($thresholdMinutes);

        Cart::where('status', 'active')
            ->where('updated_at', '<', $thresholdTime)
            ->update([
                'status' => 'pending',
                'abandoned_at' => Carbon::now()
            ]);
    }
}
