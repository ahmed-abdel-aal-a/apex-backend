<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\ShippingZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CheckoutController extends Controller
{
    public function process(\App\Http\Requests\Storefront\CheckoutProcessRequest $request)
    {
        // Validation is now handled securely by CheckoutProcessRequest

        // 2. Fetch Active Cart from Database (Strict Backend Source of Truth)
        $sessionId = $request->header('X-Session-ID');
        $customerId = auth('sanctum')->id();

        $cartQuery = Cart::with(['items.product', 'items.productVariant', 'items.printingType', 'items.addons.productAddon']);
        
        if ($customerId) {
            $cartQuery->where('customer_id', $customerId);
        } else {
            if (!$sessionId) {
                throw ValidationException::withMessages(['cart' => 'السلة فارغة أو الجلسة غير صالحة.']);
            }
            $cartQuery->where('session_id', $sessionId);
        }

        $cart = $cartQuery->where('status', 'active')->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'السلة فارغة، يرجى إضافة منتجات أولاً.']);
        }

        $lockKey = 'checkout_lock_' . ($customerId ?: $sessionId);
        $lock = Cache::lock($lockKey, 10); // 10 seconds lock

        if (!$lock->get()) {
            throw ValidationException::withMessages(['cart' => 'جاري معالجة طلبك بالفعل، يرجى الانتظار لحين الانتهاء.']);
        }

        // Resolve Coupon (from explicit request coupon_code or active cart coupon_id)
        $coupon = null;
        $couponLock = null;
        if ($request->filled('coupon_code')) {
            $couponCode = strtoupper(trim($request->coupon_code));
            $coupon = Coupon::where('code', $couponCode)->first();
            if (!$coupon) {
                $lock->release();
                throw ValidationException::withMessages(['coupon' => 'كود الخصم غير صحيح أو غير موجود.']);
            }
        } elseif ($cart->coupon_id) {
            $coupon = Coupon::find($cart->coupon_id);
        }

        if ($coupon) {
            $couponLock = Cache::lock('coupon_checkout_' . $coupon->id, 5);
            if (!$couponLock->get()) {
                $lock->release();
                throw ValidationException::withMessages(['coupon' => 'جاري معالجة طلب آخر بهذا الكوبون حالياً، يرجى المحاولة بعد لحظات.']);
            }
        }

        $copiedAddonFilePaths = [];

        try {
            DB::beginTransaction();

            // Re-fetch the cart with pessimistic locking to prevent race conditions with CartController
            $lockedCart = Cart::with(['items.product', 'items.productVariant', 'items.printingType', 'items.addons.productAddon'])
                                ->where('id', $cart->id)
                                ->lockForUpdate()
                                ->first();

            $hasPrintingItemLocked = $lockedCart->items->contains('item_type', 'printing');
            $onlinePaymentEnabled = \App\Models\PaymentGateway::where('is_active', true)
                                    ->where('is_configured', true)
                                    ->exists();
            $settings = \App\Models\Setting::getAllSettings();
            $allowFullCodForPrinting = filter_var($settings['allow_full_cod_for_printing'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($hasPrintingItemLocked && $request->payment_method === 'cod' && $onlinePaymentEnabled && !$allowFullCodForPrinting) {
                throw ValidationException::withMessages([
                    'payment_method' => 'السلة تحتوي على منتجات طباعة مخصصة، يجب سداد دفعة مقدمة (عربون) أو الدفع بالكامل إلكترونياً.'
                ]);
            }

            // We now use $lockedCart as the absolute source of truth
            $cart = $lockedCart;

            $subtotal = 0;
            $printingSubtotal = 0.00;
            $eligibleSubtotal = 0.00;
            $itemsCount = 0;
            $totalTaxAmount = '0.00';
            $orderItemsData = [];
            $reservationIds = [];
            $hasMarketSensitiveItem = false;

            // 3. Process Cart Items directly from DB
            foreach ($cart->items as $cartItem) {
                if ($cartItem->item_type === 'printing') {
                    $typeModel = $cartItem->printingType;
                    
                    if (!$typeModel) {
                        throw ValidationException::withMessages(['cart' => "نوع الطباعة غير موجود."]);
                    }

                    // [V14 Security Fix] Backend Pre-Payment Validation
                    if ($typeModel->requires_upload && $cartItem->file_status !== 'approved') {
                        throw ValidationException::withMessages([
                            'cart' => "لا يمكن إتمام الطلب، الملف المرفوع للمنتج غير معتمد أو جاري فحصه."
                        ]);
                    }

                    // [V14.1 Financial Precision & Security Fix]
                    $printingOptions = is_array($cartItem->printing_options) 
                        ? $cartItem->printing_options 
                        : (json_decode($cartItem->printing_options ?? '[]', true) ?: []);

                    $actualPhysicalQty = max(1, (int)($printingOptions['physical_quantity'] ?? 1));
                    $widthMm = (isset($printingOptions['width_mm']) && is_numeric($printingOptions['width_mm'])) 
                        ? (float)$printingOptions['width_mm'] 
                        : null;
                    $lengthMm = (isset($printingOptions['length_mm']) && is_numeric($printingOptions['length_mm'])) 
                        ? (float)$printingOptions['length_mm'] 
                        : null;
                    $imageCount = max(1, (int)($printingOptions['image_count'] ?? 1));
                    $bundleQty = max(1, (int)($cartItem->quantity ?? 1));

                    $pricingService = app(\App\Services\PrintPricingService::class);
                    $pricingResult = $pricingService->calculatePrice(
                        $cartItem->printing_type_id, 
                        $actualPhysicalQty, 
                        $printingOptions['selected_options'] ?? [], 
                        $widthMm, 
                        $lengthMm, 
                        (int)($cartItem->tax_basis_points_snapshot ?? 0),
                        $imageCount
                    );

                    $unitBundlePrice = (float)$pricingResult['final_price_egp'];
                    // إذا كان العميل يشتري حزمة واحدة، الإجمالي = سعر الحزمة مباشرة دون أي ضرب أو كسر عشري
                    $itemTotal = ($bundleQty === 1) 
                        ? $unitBundlePrice 
                        : (float)bcmul((string)$unitBundlePrice, (string)$bundleQty, 2);

                    $subtotal = (float)bcadd((string)$subtotal, (string)$itemTotal, 2);
                    $printingSubtotal = (float)bcadd((string)$printingSubtotal, (string)$itemTotal, 2);
                    $rawTaxAmount = (string)($pricingResult['tax_amount'] ?? 0);
                    $taxEgp = bcdiv($rawTaxAmount, '100', 2);
                    $bundleTax = bcmul($taxEgp, (string)$bundleQty, 2);
                    $totalTaxAmount = bcadd((string)$totalTaxAmount, (string)$bundleTax, 2);

                    $itemsCount += $bundleQty;
                    if ($coupon) {
                        $eligibleSubtotal = (float)bcadd((string)$eligibleSubtotal, (string)$itemTotal, 2);
                    }

                    $name = $cartItem->custom_product_name ?? ($typeModel ? $typeModel->name : 'طلب طباعة مخصص');
                    $isMarketSensitive = (bool)($typeModel->is_market_sensitive ?? false);
                    if ($isMarketSensitive) {
                        $hasMarketSensitiveItem = true;
                    }

                    $orderItemsData[] = [
                        'item_type' => 'printing',
                        'printing_type_id' => $cartItem->printing_type_id,
                        'custom_product_name' => $cartItem->custom_product_name,
                        'printing_options' => $printingOptions,
                        'name' => $name,
                        'quantity' => $bundleQty,
                        'price_at_purchase' => $unitBundlePrice,
                        'total' => $itemTotal,
                        'original_line_total' => $itemTotal,
                        'confirmed_line_total' => null,
                        'requires_price_confirmation' => $isMarketSensitive,
                        'price_confirmation_status' => $isMarketSensitive ? 'pending' : 'not_applicable',
                        'uploaded_files' => $cartItem->uploaded_files,
                        'file_status' => $cartItem->file_status,
                        'tax_basis_points_snapshot' => $cartItem->tax_basis_points_snapshot
                    ];
                    continue;
                }

                // Standard product logic
                $product = $cartItem->product;
                if (!$product) {
                    throw ValidationException::withMessages([
                        'cart' => "أحد المنتجات في السلة لم يعد متوفراً."
                    ]);
                }
                
                $variant = $cartItem->productVariant;
                $productName = $this->formatLocalizedName($product->name, 'المنتج');
                
                if ($product->variants()->exists() && !$variant) {
                    throw ValidationException::withMessages([
                        'cart' => "يجب اختيار مقاس أو لون للمنتج: {$productName}"
                    ]);
                }

                // Check Inventory & Lock Stock
                if ($variant) {
                    $variant->lockForUpdate();
                    if ($product->track_inventory && $variant->available_quantity < $cartItem->quantity) {
                        $varName = $this->formatLocalizedName($variant->name ?? $product->name, 'المنتج');
                        throw ValidationException::withMessages([
                            'cart' => "الكمية المطلوبة من {$varName} غير متوفرة بالمخزن."
                        ]);
                    }
                    $priceToUse = $variant->discount_price ?? $variant->price;

                    if ($product->track_inventory) {
                        $res = \App\Models\StockReservation::create([
                            'product_variant_id' => $variant->id,
                            'session_id' => $sessionId,
                            'user_id' => $customerId,
                            'quantity' => $cartItem->quantity,
                            'expires_at' => now()->addMinutes(15)
                        ]);
                        $reservationIds[] = $res->id;
                    }
                } else {
                    $product->lockForUpdate();
                    if ($product->track_inventory && $product->available_quantity < $cartItem->quantity) {
                        throw ValidationException::withMessages([
                            'cart' => "الكمية المطلوبة من {$productName} غير متوفرة بالمخزن."
                        ]);
                    }
                    $priceToUse = $product->discount_price ?? $product->price;

                    if ($product->track_inventory) {
                        $res = \App\Models\StockReservation::create([
                            'product_id' => $product->id,
                            'session_id' => $sessionId,
                            'user_id' => $customerId,
                            'quantity' => $cartItem->quantity,
                            'expires_at' => now()->addMinutes(15)
                        ]);
                        $reservationIds[] = $res->id;
                    }
                }

                $itemTotal = $priceToUse * $cartItem->quantity;
                
                // Addons calculation
                $addonSnapshots = [];
                if ($cartItem->addons->isNotEmpty()) {
                    $itemAddonIds = $cartItem->addons->pluck('product_addon_id')->toArray();
                    
                    $lockedAddons = \App\Models\ProductAddon::with('group')
                                        ->whereIn('id', $itemAddonIds)
                                        ->lockForUpdate()
                                        ->get()
                                        ->keyBy('id');

                    $reservedAddons = app(\App\Services\Storefront\CartService::class)->calculateAddonsPrice(
                        (float)$priceToUse,
                        (int)$cartItem->quantity,
                        $itemAddonIds,
                        $itemTotal,
                        $lockedAddons,
                        $addonSnapshots
                    );
                    
                    if ($reservedAddons) {
                        $reservationIds = array_merge($reservationIds, $reservedAddons);
                    }
                    
                    // Attach temp_file_ids from DB Truth (cart_item_addons) and validate files
                    foreach ($addonSnapshots as &$snap) {
                        $serverAddon = $cartItem->addons->firstWhere('product_addon_id', $snap['product_addon_id']);
                        $rawFiles = $serverAddon ? ($serverAddon->temp_file_ids ?? (!empty($serverAddon->temp_file_id) ? [$serverAddon->temp_file_id] : [])) : [];
                        
                        // فحص إلزامية الملف إذا كانت الخدمة تتطلب رفع ملف
                        $addonModel = $lockedAddons->get($snap['product_addon_id']);
                        if ($addonModel && $addonModel->requires_upload && empty($rawFiles)) {
                            $addonName = $this->formatLocalizedName($addonModel->name, 'الخدمة الإضافية');
                            throw ValidationException::withMessages([
                                'cart' => "الخدمة الإضافية ({$addonName}) تتطلب رفع ملف ولم يتم العثور على ملفات مرفوعة."
                            ]);
                        }

                        // فحص وجود الملفات على الديسك مسبقاً
                        if (!empty($rawFiles)) {
                            foreach ($rawFiles as $tempId) {
                                $tempUpload = \App\Models\TemporaryUpload::find($tempId);
                                if (!$tempUpload) {
                                    throw ValidationException::withMessages([
                                        'cart' => "أحد الملفات المرفوعة للخدمة الإضافية لم يعد متاحاً، يرجى إعادة رفع الملف."
                                    ]);
                                }
                                $uploadDisk = $tempUpload->disk ?? config('filesystems.default');
                                if (!\Illuminate\Support\Facades\Storage::disk($uploadDisk)->exists($tempUpload->file_path)) {
                                    throw ValidationException::withMessages([
                                        'cart' => "انتهت صلاحية الملف المرفوع على الخادم، يرجى إعادة رفع الملف لإتمام الطلب."
                                    ]);
                                }
                            }
                            $snap['temp_file_ids'] = $rawFiles;
                        }
                    }
                    unset($snap);
                }

                $subtotal += $itemTotal;
                $itemsCount += $cartItem->quantity; 
                if ($coupon) {
                    $isSale = !empty($variant ? $variant->discount_price : $product->discount_price);
                    if ($coupon->apply_to_sale_items || !$isSale) {
                        $eligibleSubtotal = (float)bcadd((string)$eligibleSubtotal, (string)$itemTotal, 2);
                    }
                }

                $orderItemsData[] = [
                    'item_type' => 'standard',
                    'product_id' => $product->id,
                    'variant_id' => $variant ? $variant->id : null,
                    'name' => $product->name,
                    'quantity' => $cartItem->quantity,
                    'price_at_purchase' => $priceToUse,
                    'total' => $itemTotal,
                    'addons' => $addonSnapshots,
                ];
            }

            // Calculate Coupon Discount with Atomic Conditional Increment
            $discountAmount = 0.00;
            if ($coupon) {
                $coupon->validateEligibility($eligibleSubtotal, $request->customer_phone, $cart->customer_id);

                // Atomic conditional increment in database (SQLite safe)
                $incremented = Coupon::where('id', $coupon->id)
                    ->where(function ($q) {
                        $q->whereNull('usage_limit')
                          ->orWhereColumn('times_used', '<', 'usage_limit');
                    })
                    ->increment('times_used');

                if (!$incremented) {
                    throw ValidationException::withMessages([
                        'coupon' => 'عذراً، نفد الحد الأقصى المتاح لاستخدام كود الخصم في هذه اللحظة.'
                    ]);
                }

                $discountAmount = $coupon->calculateDiscount($eligibleSubtotal);
            }

            // Calculate Shipping, COD, and Tax via dedicated ShippingCalculationService
            $shippingService = app(\App\Services\Shipping\ShippingCalculationService::class);
            $calcResult = $shippingService->calculate(
                subtotal: (string)$subtotal,
                discountAmount: (string)$discountAmount,
                zoneId: (int)$request->zone_id,
                shippingMethod: (string)$request->input('shipping_method', 'standard'),
                paymentMethod: (string)$request->payment_method,
                taxAmount: (string)$totalTaxAmount
            );

            // Calculate Deposit and Remaining Amounts with Financial Precision
            $depositPercentage = (float)($settings['deposit_percentage'] ?? 30.0);
            $minDepositAmount = (float)($settings['min_deposit_amount'] ?? 50.0);
            $depositEnabled = filter_var($settings['deposit_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $depositCalcBasis = $settings['deposit_calculation_basis'] ?? 'printing_only';
            $printingPaymentMode = $settings['printing_payment_mode'] ?? 'customer_choice';
            $grandTotal = (float)$calcResult['grand_total'];

            $isDepositRequested = ($request->input('payment_option') === 'deposit');
            if ($hasPrintingItemLocked && $printingPaymentMode === 'deposit_only') {
                $isDepositRequested = true;
            }

            $paymentType = 'full';
            $depositAmount = 0.00;
            $remainingAmount = 0.00;
            $actualDepositPercentage = null;

            $allowFullCodForPrinting = filter_var($settings['allow_full_cod_for_printing'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $hasActiveOnlineGateways = \App\Models\PaymentGateway::where('is_active', true)->exists();

            if ($request->payment_method === 'cod') {
                if ($hasPrintingItemLocked && !$allowFullCodForPrinting && $hasActiveOnlineGateways) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'عذراً، الدفع عند الاستلام بالكامل غير متاح لمنتجات الطباعة المخصصة. يرجى اختيار الدفع الإلكتروني لسداد العربون أو القيمة كاملة.'
                    ]);
                }
                $paymentType = 'cod';
                $depositAmount = 0.00;
                $remainingAmount = $grandTotal;
            } elseif ($hasPrintingItemLocked && $depositEnabled && $isDepositRequested) {
                $basis = ($depositCalcBasis === 'all_items') ? (float)$calcResult['net_items_total'] : (float)$printingSubtotal;
                $rawDeposit = (float)bcmul((string)$basis, bcdiv((string)$depositPercentage, '100', 4), 2);
                $depositCandidate = max($rawDeposit, $minDepositAmount);

                if ($grandTotal <= $minDepositAmount || $depositCandidate >= $grandTotal) {
                    $depositAmount = $grandTotal;
                    $remainingAmount = 0.00;
                    $paymentType = 'full';
                    $actualDepositPercentage = 100.00;
                } else {
                    $depositAmount = $depositCandidate;
                    $remainingAmount = (float)bcsub((string)$grandTotal, (string)number_format($depositAmount, 2, '.', ''), 2);
                    $paymentType = 'deposit';
                    $actualDepositPercentage = $depositPercentage;
                }
            } else {
                $paymentType = 'full';
                $depositAmount = 0.00;
                $remainingAmount = 0.00;
            }

            // إنشاء الطلب (Data Hijacking Prevention: Independent Data)
            $order = Order::create([
                'cart_id' => $cart->id,
                'customer_id' => $cart->customer_id,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'shipping_address' => $request->shipping_address,
                'zone_id' => $calcResult['zone_id'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'shipping_cost' => $calcResult['final_shipping_cost'],
                'shipping_method' => $calcResult['shipping_method'],
                'cod_fee' => $calcResult['cod_fee'],
                'tax_amount' => $calcResult['tax_amount'],
                'shipping_company' => $calcResult['assigned_company_name'],
                'total' => $calcResult['grand_total'],
                'payment_type' => $paymentType,
                'deposit_percentage' => $actualDepositPercentage,
                'deposit_amount' => $depositAmount,
                'remaining_amount' => $remainingAmount,
                'items_count' => $itemsCount,
                'status' => $hasMarketSensitiveItem ? 'pending_price_confirmation' : 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $request->payment_method,
                'payment_token' => $hasMarketSensitiveItem ? bin2hex(random_bytes(24)) : null,
            ]);

            // إنشاء سجل استخدام الكوبون
            if ($coupon && $discountAmount > 0) {
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'customer_id' => $cart->customer_id,
                    'customer_phone' => Coupon::normalizePhone($request->customer_phone),
                    'customer_email' => $cart->customer_email,
                    'discount_amount' => $discountAmount,
                ]);
            }

            // إنشاء عناصر الطلب
            foreach ($orderItemsData as $data) {
                $addons = $data['addons'] ?? [];
                unset($data['addons']);

                // [P0 Architectural Security Fix] عزل ونقل ملفات الطباعة لمجلد الطلب المحمي لمنع الحذف
                if (($data['item_type'] ?? '') === 'printing' && !empty($data['uploaded_files'])) {
                    $rawFiles = is_array($data['uploaded_files']) 
                        ? $data['uploaded_files'] 
                        : (json_decode($data['uploaded_files'], true) ?: []);
                    $movedFiles = [];
                    foreach ($rawFiles as $cat => $fileEntry) {
                        $filePath = is_array($fileEntry) ? ($fileEntry['path'] ?? null) : $fileEntry;
                        if (is_string($filePath) && !empty($filePath)) {
                            try {
                                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
                                    $newDir = 'order_print_jobs/' . $order->id;
                                    $newPath = $newDir . '/' . basename($filePath);
                                    \Illuminate\Support\Facades\Storage::disk('local')->move($filePath, $newPath);
                                    if (is_array($fileEntry)) {
                                        $fileEntry['path'] = $newPath;
                                        $movedFiles[$cat] = $fileEntry;
                                    } else {
                                        $movedFiles[$cat] = $newPath;
                                    }
                                } else {
                                    \Illuminate\Support\Facades\Log::warning("Checkout: Print file not found on disk: {$filePath} for order {$order->id}");
                                    $movedFiles[$cat] = $fileEntry;
                                }
                            } catch (\Throwable $e) {
                                \Illuminate\Support\Facades\Log::warning("Checkout: Failed to move print file {$filePath} for order {$order->id}: " . $e->getMessage());
                                $movedFiles[$cat] = $fileEntry;
                            }
                        } else {
                            $movedFiles[$cat] = $fileEntry;
                        }
                    }
                    $data['uploaded_files'] = $movedFiles;
                }

                $orderItem = $order->items()->create($data);
                
                foreach ($addons as $addonData) {
                    $savedFilePaths = [];
                    $disk = config('filesystems.default');

                    if (!empty($addonData['temp_file_ids'])) {
                        foreach ($addonData['temp_file_ids'] as $tempId) {
                            $tempUpload = \App\Models\TemporaryUpload::find($tempId);
                            $uploadDisk = $tempUpload->disk ?? config('filesystems.default');
                            
                            if ($tempUpload && \Illuminate\Support\Facades\Storage::disk($uploadDisk)->exists($tempUpload->file_path)) {
                                $newFilename = \Illuminate\Support\Str::random(10) . '_' . basename($tempUpload->file_path);
                                $newPath = 'addon_uploads/' . $newFilename;
                                
                                \Illuminate\Support\Facades\Storage::disk($disk)->copy($tempUpload->file_path, $newPath);
                                $savedFilePaths[] = $newPath;
                                $copiedAddonFilePaths[] = ['disk' => $disk, 'path' => $newPath];
                            }
                        }
                    }

                    // حساب كمية الإضافة بدقة مع استخدام null-safe operator
                    $addonModel = \App\Models\ProductAddon::with('group')->find($addonData['product_addon_id']);
                    $addonQty = ($addonModel?->group?->price_multiplier_type === 'per_item') 
                        ? (int)$orderItem->quantity 
                        : 1;

                    $orderItem->addons()->create([
                        'product_addon_id' => $addonData['product_addon_id'],
                        'name_snapshot' => $addonData['name'],
                        'price_snapshot' => $addonData['price_at_purchase'],
                        'price_type_snapshot' => $addonData['price_type'],
                        'quantity' => $addonQty,
                        'disk' => $disk,
                        'file_paths' => !empty($savedFilePaths) ? $savedFilePaths : null,
                    ]);
                }
            }

            // [Stock Deduction & Cart Lock Logic]
            if ($request->payment_method === 'cod') {
                $order->load('items.addons');

                // خصم المخزون الفعلي للمنتجات والمتغيرات فوراً لطلبات الدفع عند الاستلام داخل الـ transaction
                $affectedProducts = [];
                $variantDeductions = [];
                $productDeductions = [];

                foreach ($order->items as $orderItem) {
                    if ($orderItem->item_type === 'standard') {
                        if ($orderItem->variant_id) {
                            $variantDeductions[$orderItem->variant_id] = ($variantDeductions[$orderItem->variant_id] ?? 0) + $orderItem->quantity;
                        } elseif ($orderItem->product_id) {
                            $productDeductions[$orderItem->product_id] = ($productDeductions[$orderItem->product_id] ?? 0) + $orderItem->quantity;
                        }
                    }
                }

                if (!empty($variantDeductions)) {
                    $vIds = array_keys($variantDeductions);
                    sort($vIds);
                    $variants = \App\Models\ProductVariant::with('product')
                        ->whereIn('id', $vIds)
                        ->lockForUpdate()
                        ->get();

                    foreach ($variants as $variant) {
                        $qtyToDeduct = $variantDeductions[$variant->id];
                        if ($variant->track_inventory) {
                            if ($variant->quantity < $qtyToDeduct) {
                                $varName = $this->formatLocalizedName($variant->name ?? $variant->product?->name, 'المنتج');
                                throw ValidationException::withMessages([
                                    'cart' => "عذراً، نفدت كمية المنتج ({$varName}) المطلوبة بالمخزن."
                                ]);
                            }
                            $variant->decrement('quantity', $qtyToDeduct);
                        }
                        if ($variant->product) {
                            $variant->product->syncWithVariants();
                            $affectedProducts[$variant->product->id] = $variant->product;
                        }
                    }
                }

                if (!empty($productDeductions)) {
                    $pIds = array_keys($productDeductions);
                    sort($pIds);
                    $products = \App\Models\Product::whereIn('id', $pIds)
                        ->lockForUpdate()
                        ->get();

                    foreach ($products as $prod) {
                        $qtyToDeduct = $productDeductions[$prod->id];
                        if ($prod->track_inventory) {
                            if ($prod->quantity < $qtyToDeduct) {
                                $prodName = $this->formatLocalizedName($prod->name, 'المنتج');
                                throw ValidationException::withMessages([
                                    'cart' => "عذراً، نفدت كمية المنتج ({$prodName}) المطلوبة بالمخزن."
                                ]);
                            }
                            $prod->decrement('quantity', $qtyToDeduct);
                        }
                        $affectedProducts[$prod->id] = $prod;
                    }
                }

                foreach ($affectedProducts as $prod) {
                    $prod->clearStorefrontCache();
                }

                // تجميع كميات الإضافات المتتبعة للمخزون لمنع Deadlock والنزول للسالب
                $totalsByAddonId = [];
                foreach ($order->items as $orderItem) {
                    if ($orderItem->item_type === 'standard' && $orderItem->relationLoaded('addons')) {
                        foreach ($orderItem->addons as $itemAddon) {
                            if ($itemAddon->product_addon_id) {
                                $totalsByAddonId[$itemAddon->product_addon_id] = ($totalsByAddonId[$itemAddon->product_addon_id] ?? 0) + $itemAddon->quantity;
                            }
                        }
                    }
                }

                if (!empty($totalsByAddonId)) {
                    $addonIds = array_keys($totalsByAddonId);
                    sort($addonIds); // ترتيب تصاعدي لمنع Deadlock
                    $trackedAddons = \App\Models\ProductAddon::whereIn('id', $addonIds)
                        ->where('track_inventory', true)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($trackedAddons as $tAddon) {
                        $requiredQty = $totalsByAddonId[$tAddon->id];
                        if ($tAddon->quantity < $requiredQty) {
                            $addonName = $this->formatLocalizedName($tAddon->name, 'الخدمة الإضافية');
                            throw ValidationException::withMessages([
                                'cart' => "عذراً، نفدت كمية الخدمة الإضافية ({$addonName}) المطلوبة بالمخزن."
                            ]);
                        }
                        $tAddon->decrement('quantity', $requiredQty);
                    }
                }

                // حذف الحجوزات المؤقتة لمنع الخصم المزدوج
                if (!empty($reservationIds)) {
                    \App\Models\StockReservation::whereIn('id', $reservationIds)->delete();
                }

                $cart->update(['status' => 'converted']);
            } else {
                // لطلبات الدفع الإلكتروني أو طلبات مراجعة الأسعار
                if (!empty($reservationIds)) {
                    $resExpiry = $hasMarketSensitiveItem ? now()->addHours(48) : now()->addMinutes(15);
                    \App\Models\StockReservation::whereIn('id', $reservationIds)->update([
                        'order_id' => $order->id,
                        'expires_at' => $resExpiry,
                    ]);
                }

                $cart->update(['status' => 'locked']);
            }

            DB::commit();

            // إرسال إشعار لحظي لمديري النظام (Super Admins)
            try {
                $admins = \App\Models\User::role('Super Admin')->get();
                if ($admins->count() > 0) {
                    $alertTitle = $hasMarketSensitiveItem ? 'طلب طباعة بحاجة لتأكيد السعر ⏱️' : 'طلب جديد 🛍️';
                    $alertMsg = $hasMarketSensitiveItem 
                        ? "طلب جديد من {$order->customer_name} يحتوي خامات بتسعيرة متغيرة بانتظار اعتماد السعر"
                        : "تم استلام طلب جديد من {$order->customer_name} بقيمة {$order->total} ج.م";

                    \Illuminate\Support\Facades\Notification::send(
                        $admins, 
                        new \App\Notifications\Dashboard\SystemAlertNotification(
                            $alertTitle,
                            $alertMsg,
                            '/orders/' . $order->id
                        )
                    );
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('فشل إرسال إشعار الطلب: ' . $e->getMessage());
            }

            if ($cart->customer_id) {
                \App\Models\CustomerBrowsingHistory::create([
                    'store_id' => $cart->store_id ?? 1,
                    'customer_id' => $cart->customer_id,
                    'path' => '/checkout/success',
                    'title' => 'إتمام الطلب',
                    'type' => 'purchase',
                    'visited_at' => now(),
                ]);
            }

            // إذا كان الطلب خاضعاً لتسعيرة اليوم المتغيرة، يتم إرجاع الرد فوراً بدون تحويل لبوابة دفع وبدون إصدار بوليصة شحن
            if ($hasMarketSensitiveItem) {
                return response()->json([
                    'message' => 'تم استلام طلبك بنجاح وهو قيد مراجعة واعتماد السعر من فريق المطبعة.',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'requires_price_confirmation' => true,
                    'payment_token' => $order->payment_token,
                    'status' => 'pending_price_confirmation'
                ]);
            }

            // Handle Electronic Payment Gateways ('online', 'kashier', 'paymob')
            $electronicGateways = ['online', 'kashier', 'paymob'];
            if (in_array($request->payment_method, $electronicGateways, true)) {
                $gatewayCode = ($request->payment_method === 'online') 
                    ? \App\Services\Payment\PaymentGatewayManager::getActiveGatewayCode() 
                    : $request->payment_method;

                $paymentService = \App\Services\Payment\PaymentGatewayManager::resolveService($gatewayCode);
                $order->update(['payment_method' => $gatewayCode]);
                
                $paymentUrl = $paymentService->generateCheckoutUrl(
                    $order, 
                    $request->success_url, 
                    $request->failure_url
                );
                
                return response()->json([
                    'message' => 'Order created successfully',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_url' => $paymentUrl,
                    'payment_method' => $gatewayCode
                ]);
            }

            // Handle Manual Payment Transfers ('instapay', 'vodafone_cash', 'bank_transfer')
            $manualMethods = ['instapay', 'vodafone_cash', 'bank_transfer'];
            if (in_array($request->payment_method, $manualMethods, true)) {
                $token = $order->payment_token ?: bin2hex(random_bytes(24));
                $order->update([
                    'payment_status' => 'awaiting_verification',
                    'payment_token' => $token,
                    'payment_token_expires_at' => now()->addHours(48),
                ]);

                // حماية وتمديد حجز المخزون للتحويلات اليدوية لمدة 48 ساعة حتى لا يفك الكرون المخزون بعد 15 دقيقة
                if (!empty($reservationIds)) {
                    \App\Models\StockReservation::whereIn('id', $reservationIds)->update([
                        'expires_at' => now()->addHours(48),
                    ]);
                }

                return response()->json([
                    'message' => 'تم استلام طلبك بنجاح، يرجى إتمام التحويل وتأكيد الإيصال.',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_status' => 'awaiting_verification',
                    'payment_token' => $token,
                    'payment_method' => $request->payment_method
                ]);
            }

            // For Cash On Delivery & Standard orders:
            // إصدار البوليصة لا يتم في الـ Checkout بل يتم حصرياً عند تجهيز الطلب وتحويله إلى ready_for_shipping
            return response()->json([
                'message' => 'Order created successfully',
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // تنظيف أي ملفات تم نسخها حديثاً في حالة حدوث استثناء لمنع تراكم الملفات اليتيمة
            if (!empty($copiedAddonFilePaths)) {
                foreach ($copiedAddonFilePaths as $copiedFile) {
                    try {
                        \Illuminate\Support\Facades\Storage::disk($copiedFile['disk'])->delete($copiedFile['path']);
                    } catch (\Throwable $ignored) {}
                }
            }

            Log::error('Checkout Failed: ' . $e->getMessage());
            
            if ($e instanceof ValidationException) {
                throw $e;
            }
            
            return response()->json(['error' => 'حدث خطأ أثناء معالجة الطلب.'], 500);
        } finally {
            $lock->release();
            if ($couponLock) {
                $couponLock->release();
            }
        }
    }

    /**
     * Get real-time payment and order status for storefront checkout polling
     */
    public function getPaymentStatus(Request $request, $id)
    {
        $order = Order::select([
            'id', 
            'order_number', 
            'status', 
            'payment_status', 
            'payment_method', 
            'payment_type', 
            'deposit_percentage', 
            'deposit_amount', 
            'remaining_amount', 
            'total',
            'created_at'
        ])->find($id);

        if (!$order) {
            return response()->json(['error' => 'الطلب غير موجود.'], 404);
        }

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'payment_type' => $order->payment_type,
            'deposit_percentage' => $order->deposit_percentage ? (float)$order->deposit_percentage : null,
            'deposit_amount' => (float)($order->deposit_amount ?? 0),
            'remaining_amount' => (float)($order->remaining_amount ?? 0),
            'total' => (float)$order->total,
            'created_at' => $order->created_at->toIso8601String(),
        ]);
    }

    private function formatLocalizedName(mixed $val, string $default = 'المنتج'): string
    {
        if (is_array($val)) {
            $str = $val['ar'] ?? reset($val);
            return is_string($str) ? $str : $default;
        }
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $str = $decoded['ar'] ?? reset($decoded);
                return is_string($str) ? $str : $default;
            }
            return $val ?: $default;
        }
        return $default;
    }
}
