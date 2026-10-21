<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MarkAbandonedCarts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'carts:mark-abandoned';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marks active carts that have been inactive for the configured threshold as pending (abandoned)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        \App\Jobs\ProcessAbandonedCarts::dispatch();
        $this->info('ProcessAbandonedCarts job dispatched.');
    }
}
