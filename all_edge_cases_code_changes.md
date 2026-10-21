# سجل التعديلات الشامل (100% المطابق لما تم تنفيذه)

هذا الملف يحتوي على كل حرف وكل سطر كود تم إضافته أو تعديله في النظام لتنفيذ مهام الـ Edge Cases (الملفات الضخمة، التنظيف التلقائي، وحماية المخزون).

---

## 1. إنشاء `ChunkUploadController` لرفع الملفات الضخمة بدون استهلاك الرامات
**المسار:** `backend/app/Http/Controllers/Api/Storefront/ChunkUploadController.php`
**الكود بالكامل (كُتب من الصفر):**

```php
<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\TemporaryUpload;

class ChunkUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'resumableIdentifier' => 'required|string',
            'resumableChunkNumber' => 'required|integer',
            'resumableTotalChunks' => 'required|integer',
            'resumableFilename' => 'required|string',
            'category' => 'required|string'
        ]);

        $file = $request->file('file');
        $identifier = $request->input('resumableIdentifier');
        $chunkNumber = (int) $request->input('resumableChunkNumber');
        $totalChunks = (int) $request->input('resumableTotalChunks');
        $filename = $request->input('resumableFilename');
        $category = $request->input('category');

        $chunkDir = 'chunks/' . $identifier;
        if (!Storage::disk('local')->exists($chunkDir)) {
            Storage::disk('local')->makeDirectory($chunkDir);
        }

        // Save chunk
        $chunkPath = $chunkDir . '/' . $chunkNumber;
        Storage::disk('local')->putFileAs($chunkDir, $file, $chunkNumber);

        // Check if all chunks are uploaded
        $uploadedChunks = count(Storage::disk('local')->files($chunkDir));

        if ($uploadedChunks === $totalChunks) {
            // Concatenate all chunks
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'tmp';
            }
            
            $finalFilename = Str::uuid() . '.' . $extension;
            $finalPath = 'temp_uploads/' . $finalFilename;
            $absoluteFinalPath = Storage::disk('public')->path($finalPath);

            // Create temp_uploads directory if not exists
            if (!Storage::disk('public')->exists('temp_uploads')) {
                Storage::disk('public')->makeDirectory('temp_uploads');
            }

            // Open final file for writing
            $finalFile = fopen($absoluteFinalPath, 'ab');

            for ($i = 1; $i <= $totalChunks; $i++) {
                $chunkFile = Storage::disk('local')->path($chunkDir . '/' . $i);
                $chunkHandle = fopen($chunkFile, 'rb');
                stream_copy_to_stream($chunkHandle, $finalFile);
                fclose($chunkHandle);
            }

            fclose($finalFile);

            // Clean up chunks
            Storage::disk('local')->deleteDirectory($chunkDir);

            // Save to DB
            $uploadId = Str::uuid()->toString();
            $tempUpload = TemporaryUpload::create([
                'id' => $uploadId,
                'session_id' => $request->header('X-Session-ID'),
                'user_id' => auth()->id(),
                'file_path' => $finalPath,
                'category' => $category,
                'page_count' => null // Optional PDF parsing could go here
            ]);

            return response()->json([
                'message' => 'Upload complete',
                'file_id' => $uploadId,
                'file_path' => Storage::disk('public')->url($finalPath),
                'status' => 'completed'
            ]);
        }

        return response()->json([
            'message' => 'Chunk ' . $chunkNumber . ' uploaded successfully',
            'status' => 'uploading'
        ]);
    }
}
```

---

## 2. تسجيل مسار الـ Chunk Upload في الـ Routes
**المسار:** `backend/routes/api.php`
**التعديلات التي تمت:**

- تم إضافة الاستدعاء الخاص بالكنترولر في الأعلى (سطر 167):
```php
use App\Http\Controllers\Api\Storefront\ChunkUploadController;
```

- تم إضافة الـ Route في قسم `Storefront Public Routes` تحت `/print` (سطر 192):
```php
    Route::post('/print/chunk-upload', [ChunkUploadController::class, 'upload']);
```

---

## 3. إنشاء `CleanOldPrintFiles` لحذف ملفات الطلبات المكتملة القديمة
**المسار:** `backend/app/Console/Commands/CleanOldPrintFiles.php`
**الكود بالكامل (كُتب من الصفر):**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanOldPrintFiles extends Command
{
    protected $signature = 'print:clean-old-files';
    protected $description = 'Clean up print files for completed/delivered orders older than 7 days';

    public function handle()
    {
        $cutoffDate = Carbon::now()->subDays(7);
        $this->info("Cleaning files older than: {$cutoffDate}");

        // Find completed or delivered orders older than 7 days
        $orders = Order::whereIn('status', ['completed', 'delivered', 'cancelled', 'refunded'])
            ->where('updated_at', '<', $cutoffDate)
            ->with(['items.addons'])
            ->get();

        $deletedFiles = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                // Handle Item Files
                if ($item->item_type === 'printing' && $item->uploaded_files) {
                    $files = is_string($item->uploaded_files) ? json_decode($item->uploaded_files, true) : $item->uploaded_files;
                    
                    if (is_array($files)) {
                        foreach ($files as $index => $fileObj) {
                            if (isset($fileObj['path']) && Storage::disk('public')->exists($fileObj['path'])) {
                                Storage::disk('public')->delete($fileObj['path']);
                                $deletedFiles++;
                                // Mark as deleted to avoid re-checking or breaking the UI
                                $files[$index]['path'] = 'deleted_by_system';
                            }
                        }
                        $item->uploaded_files = $files;
                        $item->file_status = 'cleaned';
                        $item->save();
                    }
                }

                // Handle Addon Files
                foreach ($item->addons as $addon) {
                    if (!empty($addon->file_path) && Storage::disk('public')->exists($addon->file_path)) {
                        Storage::disk('public')->delete($addon->file_path);
                        $addon->file_path = 'deleted_by_system';
                        $addon->save();
                        $deletedFiles++;
                    }
                }
            }
        }

        $this->info("Cleanup completed. Deleted {$deletedFiles} files.");
    }
}
```

---

## 4. إضافة أمر التنظيف لجدولة الأوامر (Scheduler)
**المسار:** `backend/routes/console.php`
**التعديل الذي تم:**
تمت إضافة سطر لتشغيل أمر التنظيف بشكل يومي في نهاية الملف:

```php
Schedule::command('print:clean-old-files')->daily();
```

---

## 5. قفل العملية (Redis Lock) لمنع الـ Race Conditions في الـ Checkout
**المسار:** `backend/app/Http/Controllers/Api/Storefront/CheckoutController.php`
**التعديلات التي تمت:**

- تم استدعاء `Cache` في الأعلى لحماية العمليات باستخدام واجهة Redis/Cache:
```php
use Illuminate\Support\Facades\Cache;
```

- تمت إضافة الحماية بالـ Lock في السطر 56 قبل عمل الـ `DB::beginTransaction()`:
```php
        $lockKey = 'checkout_lock_' . ($userId ?: $sessionId);
        $lock = Cache::lock($lockKey, 10); // 10 seconds lock

        if (!$lock->get()) {
            throw ValidationException::withMessages(['cart' => 'جاري معالجة طلبك بالفعل، يرجى الانتظار لحين الانتهاء.']);
        }
```

- تم إغلاق (Release) الـ Lock بشكل حتمي في حالة الخطأ أو النجاح باستخدام `finally` في نهاية الكنترولر (السطر 326):
```php
        } finally {
            $lock->release();
        }
```

---
**كل حرف وكلمة موجودة في هذا الملف تطابق تماماً بنسبة 100% ما تم تنفيذه داخل مشروعك لتأسيس قواعد الحماية ومعالجة الـ Edge Cases.**
