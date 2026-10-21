<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PrintingProductType;
use App\Models\PrintingType;
use App\Services\PrintPricingService;
use App\Models\CartItem;
use App\Services\Storefront\CartService;
use App\Jobs\ValidatePrintFileJob;

class PrintStationController extends Controller
{
    public function getPrintingProductTypes()
    {
        $types = PrintingProductType::with(['printingTypes' => function($q) {
            $q->where('is_active', true)->orderBy('sort_order');
        }])->where('is_active', true)->orderBy('sort_order')->get();

        return response()->json(['data' => $types]);
    }

    public function getPrintingTypeDetails($id)
    {
        $printingType = PrintingType::with(['attributes.values' => function($q) {
            $q->orderBy('sort_order');
        }])->where('is_active', true)->findOrFail($id);
        
        return response()->json(['data' => $printingType]);
    }

    public function calculateLivePrice(Request $request, PrintPricingService $pricingService)
    {
        $validated = $request->validate([
            'printing_type_id' => 'required|exists:printing_types,id',
            'quantity' => 'required|integer|min:1',
            'selected_options' => 'present|array',
            'selected_options.*.value_id' => 'required|integer',
            'selected_options.*.multiplier_value' => 'nullable|numeric|min:1|max:10000',
            'width_mm' => 'nullable|numeric',
            'length_mm' => 'nullable|numeric',
            'image_count' => 'nullable|integer|min:1',
        ]);

        $printingType = PrintingType::findOrFail($validated['printing_type_id']);
        $maxFilesLimit = $printingType->upload_rules['max_files_limit'] ?? null;
        if ($maxFilesLimit !== null && isset($validated['image_count']) && $validated['image_count'] > $maxFilesLimit) {
            return response()->json(['message' => 'لقد تجاوزت الحد الأقصى المسموح لعدد الصور/الملفات وهو ' . $maxFilesLimit], 422);
        }

        $taxBasisPoints = 0; // Live price can be exclusive of tax or we can fetch from settings

        $result = $pricingService->calculatePrice(
            $validated['printing_type_id'],
            $validated['quantity'],
            $validated['selected_options'],
            $validated['width_mm'] ?? null,
            $validated['length_mm'] ?? null,
            $taxBasisPoints,
            $validated['image_count'] ?? 1
        );

        return response()->json($result);
    }

    public function tempUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,ai,psd,zip,rar,jpg,jpeg,png,webp|max:512000', // 500MB max
            'category' => 'required|string',
            'session_id' => 'required_without:user_id|string',
            'user_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $mime = $file->getMimeType();

        // Strict ZIP Validation (Zip Bomb & Executable check)
        if ($extension === 'zip' || $mime === 'application/zip') {
            $zip = new \ZipArchive();
            if ($zip->open($file->getRealPath()) === true) {
                $totalUncompressed = 0;
                $blockedExtensions = ['exe', 'bat', 'sh', 'php', 'js', 'vbs'];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $totalUncompressed += $stat['size'];
                    $ext = strtolower(pathinfo($stat['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $blockedExtensions)) {
                        $zip->close();
                        return response()->json(['error' => 'Zip file contains blocked executable files.'], 422);
                    }
                }
                $zip->close();
                // Check if uncompressed size is larger than 2GB (Zip Bomb prevention)
                if ($totalUncompressed > 2 * 1024 * 1024 * 1024) {
                    return response()->json(['error' => 'Zip bomb detected. Uncompressed size is too large.'], 422);
                }
            }
        }

        $pageCount = null;
        if ($extension === 'pdf' || $mime === 'application/pdf') {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($file->getRealPath());
                $pageCount = count($pdf->getPages());

                if ($pageCount === 0) {
                    throw new \Exception("PDF has 0 pages.");
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('PDF parsing failed via smalot/pdfparser: ' . $e->getMessage());
                return response()->json(['error' => 'الملف بصيغة PDF معطوب أو مشفر، يرجى التأكد من الملف وإعادة الرفع مرة أخرى.'], 422);
            }
        }

        $path = $file->store('temp_uploads', 'local');

        $tempUpload = \App\Models\TemporaryUpload::create([
            'session_id' => $request->session_id,
            'user_id' => $request->user_id,
            'file_path' => $path,
            'category' => $request->category,
            'page_count' => $pageCount,
        ]);

        return response()->json([
            'file_id' => $tempUpload->id,
            'page_count' => $pageCount,
            'category' => $tempUpload->category,
        ]);
    }

    public function addToCart(Request $request, CartService $cartService, PrintPricingService $pricingService)
    {
        $printingType = $request->printing_type_id ? PrintingType::find($request->printing_type_id) : null;

        $validated = $request->validate([
            'printing_type_id' => 'required|exists:printing_types,id',
            'quantity' => 'required|integer|min:1',
            'selected_options' => 'present|array',
            'selected_options.*.value_id' => 'required|integer',
            'selected_options.*.multiplier_value' => 'nullable|numeric|min:1|max:10000',
            'width_mm' => 'nullable|numeric',
            'length_mm' => 'nullable|numeric',
            'image_count' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'temp_file_ids' => ($printingType && $printingType->requires_upload) ? 'required|array|min:1' : 'nullable|array',
            'temp_file_ids.*' => 'required|string|uuid|exists:temporary_uploads,id',
            'session_id' => 'required|string',
        ]);

        if (!$printingType) {
            $printingType = PrintingType::findOrFail($validated['printing_type_id']);
        }

        $maxFilesLimit = $printingType->upload_rules['max_files_limit'] ?? null;
        if ($maxFilesLimit !== null && isset($validated['image_count']) && $validated['image_count'] > $maxFilesLimit) {
            return response()->json(['message' => 'لقد تجاوزت الحد الأقصى المسموح لعدد الصور/الملفات وهو ' . $maxFilesLimit], 422);
        }

        $movedFiles = [];

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $cartService, $pricingService, $request, $printingType, &$movedFiles) {
                $cart = $cartService->getOrCreateCart($validated['session_id'], null, $request->ip(), $request->userAgent());

                $taxBasisPoints = 0;
                $pricing = $pricingService->calculatePrice(
                    $validated['printing_type_id'],
                    $validated['quantity'],
                    $validated['selected_options'],
                    $validated['width_mm'] ?? null,
                    $validated['length_mm'] ?? null,
                    $taxBasisPoints,
                    $validated['image_count'] ?? 1
                );

                // 1. الحماية من تسعير الصفر (Zero-Price Guard)
                if ((float)$pricing['final_price_egp'] <= 0) {
                    return response()->json(['message' => 'تكلفة الطلب غير صحيحة أو أقل من الحد الأدنى. يرجى إعادة التكوين.'], 422);
                }

                $customName = $printingType ? $printingType->name : 'طلب طباعة مخصص';

                // 2. استخدام التعيين المباشر الصارم
                $cartItem = new CartItem();
                $cartItem->cart_id = $cart->id;
                $cartItem->product_id = null; 
                $cartItem->item_type = 'printing'; // [Fixed mismatch: CheckoutController expects 'printing']
                $cartItem->printing_type_id = $validated['printing_type_id'];
                $cartItem->custom_product_name = $customName; 
                $cartItem->quantity = 1;
                $cartItem->price = (float)$pricing['final_price_egp'];
                $cartItem->quote_expires_at = now()->addDays(3); // التسعيرة صالحة لـ 3 أيام

                // الحفاظ على هيكل الـ Schema نظيفاً وإغلاق ثغرة الاندكس المفقود
                $cartItem->printing_options = [
                    'selected_options' => $validated['selected_options'] ?? [],
                    'physical_quantity' => $validated['quantity'],
                    'width_mm' => $validated['width_mm'] ?? null,
                    'length_mm' => $validated['length_mm'] ?? null,
                    'image_count' => $validated['image_count'] ?? 1,
                    'notes' => $validated['notes'] ?? null,
                ];

                // اعتماد الملف فوراً بعد اجتياز الفحص الأمني اللحظي (MIME + Magic Bytes + Zip Bomb + PDF Parser)
                $cartItem->file_status = 'approved';
                $cartItem->uploaded_files = [];

                // تنفيذ الحفظ بأمان
                $cartItem->save();

                $uploadedFilesArr = [];
                $tempFileIds = $validated['temp_file_ids'] ?? [];

                if (!empty($tempFileIds)) {
                    foreach ($tempFileIds as $fileId) {
                        $tempFile = \App\Models\TemporaryUpload::where('id', $fileId)
                            ->where(function($q) use ($validated) {
                                $q->where('session_id', $validated['session_id'])
                                  ->orWhere('user_id', auth()->id());
                            })->first();

                        if (!$tempFile) {
                            throw new \Exception("Invalid or unauthorized file ID: {$fileId}");
                        }

                        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($tempFile->file_path)) {
                            $newPath = 'print_jobs/' . $cartItem->id . '/' . basename($tempFile->file_path);
                            \Illuminate\Support\Facades\Storage::disk('local')->move($tempFile->file_path, $newPath);
                            $movedFiles[] = ['from' => $tempFile->file_path, 'to' => $newPath];
                            $uploadedFilesArr[$tempFile->category] = $newPath;
                        }
                        
                        $tempFile->delete();
                    }
                }

                $cartItem->update(['uploaded_files' => $uploadedFilesArr]);
                $cart->calculateTotalAmount();
                app(\App\Services\Storefront\CartService::class)->syncAndValidateCoupon($cart);

                // فحص Ghostscript غير متزامن إضافي فقط في حال تفعيله صراحة من الإعدادات
                if (config('services.ghostscript.enabled') === true && !empty($uploadedFilesArr)) {
                    foreach ($uploadedFilesArr as $filePath) {
                        if (str_ends_with(strtolower($filePath), '.pdf')) {
                            ValidatePrintFileJob::dispatch($cartItem->id, $filePath);
                        }
                    }
                }

                return response()->json(['message' => 'Job added to cart successfully', 'cart_item_id' => $cartItem->id]);
            });
        } catch (\Exception $e) {
            // File System Rollback
            foreach ($movedFiles as $file) {
                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($file['to'])) {
                    \Illuminate\Support\Facades\Storage::disk('local')->move($file['to'], $file['from']);
                }
            }
            throw $e;
        }
    }

    public function checkFileStatus($cartItemId)
    {
        $cartItem = CartItem::findOrFail($cartItemId);
        return response()->json(['status' => $cartItem->file_status]);
    }

    public function deleteUnlinkedFile(Request $request, $cartItemId, $category)
    {
        $cartItem = CartItem::with('cart')->findOrFail($cartItemId);
        $cart = $cartItem->cart;

        // الحماية الأمنية من التلاعب بالمعرفات (Anti-IDOR Authorization Check)
        $sessionId = $request->header('X-Session-ID') ?: $request->input('session_id');
        $userId = auth()->id();

        $isAuthorized = false;
        if ($cart) {
            if ($sessionId && $cart->session_id === $sessionId) {
                $isAuthorized = true;
            } elseif ($userId && $cart->customer_id === $userId) {
                $isAuthorized = true;
            } elseif (!$sessionId && !$userId && $cart->ip_address === $request->ip()) {
                $isAuthorized = true;
            }
        }

        if (!$isAuthorized) {
            return response()->json(['message' => 'غير مصرح لك بتعديل هذا العنصر.'], 403);
        }

        // الحماية من ثغرات Path Traversal: استخراج المسار حصرياً من سجل قاعدة البيانات
        $uploadedFiles = is_array($cartItem->uploaded_files)
            ? $cartItem->uploaded_files
            : (json_decode($cartItem->uploaded_files ?? '[]', true) ?: []);

        if (isset($uploadedFiles[$category]) && is_string($uploadedFiles[$category]) && !empty($uploadedFiles[$category])) {
            $filePath = $uploadedFiles[$category];

            // الحذف الفعلي والآمن للملف من القرص المحلي local
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($filePath);
            }

            // إزالة الفئة من القاموس وتحديث قاعدة البيانات
            unset($uploadedFiles[$category]);
            $cartItem->uploaded_files = $uploadedFiles;

            // إذا كان نوع الطباعة يتطلب رفع ملفات وأصبحت القائمة فارغة، تُعاد الحالة إلى pending
            $type = $cartItem->printingType;
            if ($type && $type->requires_upload && empty($uploadedFiles)) {
                $cartItem->file_status = 'pending';
            }

            $cartItem->save();
        }

        return response()->json([
            'message' => 'تم حذف الملف بنجاح.',
            'uploaded_files' => $cartItem->uploaded_files
        ]);
    }
}
