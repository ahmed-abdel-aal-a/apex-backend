<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RevalidateScheduledOffersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'offers:revalidate-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks if any active offer starts or ends this minute and triggers storefront SSG revalidation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Timezone Fix: Ensure we are comparing with UTC (how dates are stored in DB)
        $now = now()->timezone('UTC');
        
        // 2. Buffer Fix: Check a window of the last 5 minutes in case the cron was delayed
        $startTime = $now->copy()->subMinutes(5)->startOfMinute();
        $endTime = $now->copy()->endOfMinute();

        $hasScheduledTransition = \App\Models\Offer::where('is_active', true)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_date', [$startTime, $endTime])
                      ->orWhereBetween('end_date', [$startTime, $endTime]);
            })->exists();

        if ($hasScheduledTransition) {
            \App\Jobs\RevalidateStorefrontOffersJob::dispatch();
            $this->info('Scheduled offer transition detected. Revalidation job dispatched.');
        } else {
            $this->info('No scheduled transitions this minute.');
        }
    }
}
