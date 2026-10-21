<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevalidateStorefrontOffersJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public $uniqueFor = 60; // unique for 60 seconds

    public function uniqueId(): string
    {
        return 'offers-revalidation';
    }

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
        $storefrontUrl = config('services.storefront.url');
        $secret = config('services.storefront.secret', 'my-super-secret-token');

        if (!$storefrontUrl) {
            Log::warning('Storefront URL not configured. Cannot revalidate offers.');
            return;
        }

        try {
            $response = Http::post("{$storefrontUrl}/api/revalidate", [
                'secret' => $secret,
                'tag' => 'offers' // Assuming we will update the Next.js endpoint to accept a tag
            ]);

            if ($response->failed()) {
                Log::error('Failed to revalidate storefront offers.', ['response' => $response->body()]);
                // Depending on strictness, we could throw an exception to retry
                // throw new \Exception('Webhook failed');
            }
        } catch (\Exception $e) {
            Log::error('Error revalidating storefront offers: ' . $e->getMessage());
            // throw $e; // Throwing will cause the job to retry based on queue config
        }
    }
}
