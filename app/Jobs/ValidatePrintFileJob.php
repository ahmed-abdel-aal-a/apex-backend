<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CartItem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ValidatePrintFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * V14: حماية من قنبلة الـ PDF بحد أقصى 120 ثانية
     * @var int
     */
    public $timeout = 120;

    /**
     * لا يُعاد المحاولة لتجنب استنزاف الموارد
     * @var int
     */
    public $tries = 1;
    
    /**
     * @var int
     */
    public $maxExceptions = 1;

    protected $cartItemId;
    protected $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct(int $cartItemId, string $filePath)
    {
        $this->cartItemId = $cartItemId;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cartItem = CartItem::find($this->cartItemId);
        
        if (!$cartItem) {
            return;
        }

        // إذا كانت خدمة Ghostscript معطلة في الإعدادات، نعتمد الملف مباشرة دون إرهاق السيرفر
        if (!config('services.ghostscript.enabled', false)) {
            $cartItem->update([
                'file_status' => 'approved',
                'file_rejection_reason' => null
            ]);
            event(new \App\Events\PrintFileValidationCompleted($this->cartItemId, 'approved'));
            return;
        }

        if (!Storage::disk('local')->exists($this->filePath)) {
            Log::warning("ValidatePrintFileJob: File not found on local disk: {$this->filePath}");
            return;
        }

        $fullPath = Storage::disk('local')->path($this->filePath);
        $gsBinary = config('services.ghostscript.path') ?: 'gs';

        try {
            $cartItem->update(['file_status' => 'processing']);
            
            // تنفيذ Ghostscript في وضع Sandbox
            $result = Process::timeout(60)->run([
                $gsBinary, '-dSAFER', '-dNOPAUSE', '-dBATCH', '-sDEVICE=nullpage', $fullPath
            ]);

            if ($result->successful()) {
                $cartItem->update([
                    'file_status' => 'approved',
                    'file_rejection_reason' => null
                ]);
            } else {
                $cartItem->update([
                    'file_status' => 'rejected',
                    'file_rejection_reason' => 'فشل في تحليل الملف: ' . ($result->errorOutput() ?: $result->output())
                ]);
                Storage::disk('local')->delete($this->filePath);
            }
            
            event(new \App\Events\PrintFileValidationCompleted($this->cartItemId, $cartItem->file_status));
            
        } catch (\Exception $e) {
            Log::error('PDF Validation Failed: ' . $e->getMessage());
            $cartItem->update([
                'file_status' => 'rejected',
                'file_rejection_reason' => 'حدث خطأ أثناء فحص الملف: ' . $e->getMessage()
            ]);
            Storage::disk('local')->delete($this->filePath);
            event(new \App\Events\PrintFileValidationCompleted($this->cartItemId, 'rejected'));
        }
    }
}
