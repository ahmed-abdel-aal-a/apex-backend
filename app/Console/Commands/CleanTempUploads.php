<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanTempUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'print:clean-temp';

    protected $description = 'Clean temporary uploads older than 3 hours';

    public function handle()
    {
        $cutoff = now()->subHours(3);
        $expired = \App\Models\TemporaryUpload::where('created_at', '<', $cutoff)->get();
        
        $count = 0;
        foreach ($expired as $upload) {
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($upload->file_path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($upload->file_path);
            }
            $upload->delete();
            $count++;
        }
        
        $this->info("Cleaned {$count} expired temporary uploads.");
    }
}
