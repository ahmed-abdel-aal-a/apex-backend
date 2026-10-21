<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PruneCustomerHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:prune-history {--days=60 : Days to keep history for non-buyers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old customer browsing history to prevent database bloat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) ($this->option('days') ?: 60);
        $this->info("Starting pruning of customer browsing history (older than {$days} days for non-buyers)...");

        $threshold = Carbon::now()->subDays($days);
        
        // 1. Delete history older than threshold for visitors/customers who NEVER purchased (total_orders = 0)
        $nonBuyerIds = DB::table('customers')
            ->where('total_orders', 0)
            ->pluck('id');

        $deletedNonBuyers = 0;
        if ($nonBuyerIds->isNotEmpty()) {
            $deletedNonBuyers = DB::table('customer_browsing_histories')
                ->whereIn('customer_id', $nonBuyerIds)
                ->where('created_at', '<', $threshold)
                ->delete();
        }

        $this->info("Deleted {$deletedNonBuyers} history records for non-buyers (older than {$days} days).");

        // 2. Delete history older than 1 year for ALL users (even buyers)
        $oneYearAgo = Carbon::now()->subYear();
        
        $deletedOld = DB::table('customer_browsing_histories')
            ->where('created_at', '<', $oneYearAgo)
            ->delete();

        $this->info("Deleted {$deletedOld} history records for all users (older than 1 year).");

        $this->info('Pruning completed successfully.');
    }
}
