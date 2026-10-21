<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockReservation;

class CleanExpiredReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired stock reservations to free up inventory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deleted = StockReservation::where('expires_at', '<', now())->delete();
        $this->info("Cleaned {$deleted} expired stock reservations.");
    }
}
