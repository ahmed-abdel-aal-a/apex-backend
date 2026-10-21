<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use App\Notifications\FileRejectedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use App\Services\Storefront\OrderPricingService;

class OrderController extends Controller
{
    private function applyFilters(Request $request, $query)
    {
        // Payment logic
        if ($request->payment && $request->payment !== 'all') {
            $query->where('payment_status', $request->payment);
        }

        // Date logic
        if ($request->date && $request->date !== 'all') {
            if ($request->date === 'today') {
                $query->whereDate('created_at', today());
            } elseif ($request->date === 'week') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($request->date === 'month') {
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
            } elseif ($request->date === 'year') {
                $query->whereYear('created_at', now()->year);
            }
        }

        // Search logic
        if ($request->search) {
            $search = $request->search;
            
            // استخلاص الأرقام فقط من نص البحث (لعلاج مشكلة #ORD- والأصفار)
            $cleanId = preg_replace('/[^0-9]/', '', $search);

            $query->where(function ($q) use ($search, $cleanId) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
                  
                // البحث بالـ ID فقط إذا كان هناك أرقام مستخلصة
                if (!empty($cleanId)) {
                    $q->orWhere('id', (int)$cleanId);
                }
            });
        }
    }

    public function index(Request $request)
    {
        $query = Order::query();

        // Handle 'trash' status using soft deletes
        if ($request->status === 'trash') {
            $query->onlyTrashed();
        } else {
            if ($request->status && $request->status !== 'all') {
                if ($request->status === 'needs_action') {
                    $query->whereIn('status', ['needs_action', 'pending']);
                } elseif ($request->status === 'completed') {
                    $query->whereIn('status', ['completed', 'delivered']);
                } elseif ($request->status === 'cancelled') {
                    $query->whereIn('status', ['cancelled', 'refunded']);
                } else {
                    $query->where('status', $request->status);
                }
            }
        }

        $this->applyFilters($request, $query);

        // Include relationships and paginate
        $orders = $query->with(['items.addons', 'items.printingType'])->orderBy('created_at', 'desc')->paginate(15);
        $orders->getCollection()->append('order_number');

        return response()->json($orders);
    }

    public function summary(Request $request)
    {
        $baseQuery = Order::query();
        $this->applyFilters($request, $baseQuery);

        $statusCounts = (clone $baseQuery)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Harmonize status counts with dashboard tabs
        $statusCounts['needs_action'] = ($statusCounts['needs_action'] ?? 0) + ($statusCounts['pending'] ?? 0);
        $statusCounts['completed'] = ($statusCounts['completed'] ?? 0) + ($statusCounts['delivered'] ?? 0);
        $statusCounts['cancelled'] = ($statusCounts['cancelled'] ?? 0) + ($statusCounts['refunded'] ?? 0);
        $statusCounts['pending_price_confirmation'] = $statusCounts['pending_price_confirmation'] ?? 0;
        $statusCounts['confirmed'] = $statusCounts['confirmed'] ?? 0;
        $statusCounts['processing'] = $statusCounts['processing'] ?? 0;
        $statusCounts['shipped'] = $statusCounts['shipped'] ?? 0;

        $trashedCount = (clone $baseQuery)->onlyTrashed()->count();
        $statusCounts['trash'] = $trashedCount;
        
        $totalOrders = (clone $baseQuery)->count();
        $totalSales = (clone $baseQuery)->where('payment_status', 'paid')->sum('total');
        $shippedToday = (clone $baseQuery)->where('status', 'shipped')->whereDate('created_at', today())->count();
        
        $statusCounts['total_orders'] = $totalOrders;
        $statusCounts['total_sales'] = $totalSales;
        $statusCounts['shipped_today'] = $shippedToday;

        return response()->json($statusCounts);
    }

    public function export(Request $request)
    {
        $query = Order::query();

        if ($request->status === 'trash') {
            $query->onlyTrashed();
        } elseif ($request->status && $request->status !== 'all') {
            if ($request->status === 'needs_action') {
                $query->whereIn('status', ['needs_action', 'pending']);
            } elseif ($request->status === 'completed') {
                $query->whereIn('status', ['completed', 'delivered']);
            } elseif ($request->status === 'cancelled') {
                $query->whereIn('status', ['cancelled', 'refunded']);
            } else {
                $query->where('status', $request->status);
            }
        }

        $this->applyFilters($request, $query);
        $orders = $query->orderBy('created_at', 'desc')->get();
        $orders->append('order_number');

        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel Arabic support
        $csv .= "sep=,\n"; // Excel column separator directive
        $csv .= "رقم الطلب,اسم العميل,رقم الجوال,الإجمالي,المنتجات,الحالة,حالة الدفع,شركة الشحن,التاريخ\n";

        foreach ($orders as $order) {
            $statusLabel = match($order->status) {
                'pending', 'needs_action' => 'تحتاج إجراء',
                'pending_price_confirmation' => 'بانتظار تأكيد السعر',
                'confirmed' => 'تم التأكيد',
                'processing' => 'قيد التنفيذ',
                'shipped' => 'تم الشحن',
                'delivered', 'completed' => 'مكتمل',
                'cancelled' => 'ملغي',
                'refunded' => 'مسترد',
                default => $order->status,
            };
            $csv .= "{$order->order_number},\"{$order->customer_name}\",\"{$order->customer_phone}\",{$order->total},{$order->items_count},\"{$statusLabel}\",\"{$order->payment_status}\",\"{$order->shipping_company}\",\"{$order->created_at}\"\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="orders_export.csv"');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string'
        ]);

        $cleanId = preg_replace('/[^0-9]/', '', (string)$id);
        $orderIdToFind = !empty($cleanId) ? (int)$cleanId : $id;

        return DB::transaction(function () use ($orderIdToFind, $request) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($orderIdToFind);
            $oldStatus = $order->status;
            $newStatus = $request->status;

            // استرجاع المخزون عند إلغاء أو استرداد الطلب (مع الحماية من التكرار)
            if (in_array($newStatus, ['cancelled', 'refunded']) && !in_array($oldStatus, ['cancelled', 'refunded'])) {
                // استرجاع استخدام الكوبون المحسوب مع الحماية الصارمة من التكرار (Idempotency)
                if ($order->coupon_id) {
                    $deleted = \App\Models\CouponUsage::where('order_id', $order->id)->delete();
                    if ($deleted > 0) {
                        \App\Models\Coupon::where('id', $order->coupon_id)
                            ->where('times_used', '>', 0)
                            ->decrement('times_used');
                    }
                }

                // استرجاع المخزون الفعلي فقط إذا كان الطلب قد خُصم منه المخزون (COD أو تم دفعه أو دفع عربونه)
                if ($order->payment_method === 'cod' || in_array($order->payment_status, ['paid', 'partially_paid'])) {
                    $order->load('items.addons');

                    $affectedProducts = [];
                    $variantRestitutions = [];
                    $productRestitutions = [];

                    foreach ($order->items as $orderItem) {
                        if ($orderItem->item_type === 'standard') {
                            if ($orderItem->variant_id) {
                                $variantRestitutions[$orderItem->variant_id] = ($variantRestitutions[$orderItem->variant_id] ?? 0) + $orderItem->quantity;
                            } elseif ($orderItem->product_id) {
                                $productRestitutions[$orderItem->product_id] = ($productRestitutions[$orderItem->product_id] ?? 0) + $orderItem->quantity;
                            }
                        }
                    }

                    if (!empty($variantRestitutions)) {
                        $vIds = array_keys($variantRestitutions);
                        sort($vIds);
                        $variants = \App\Models\ProductVariant::with('product')
                            ->whereIn('id', $vIds)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                        foreach ($variants as $variant) {
                            if ($variant->track_inventory) {
                                $variant->increment('quantity', $variantRestitutions[$variant->id]);
                            }
                            if ($variant->product) {
                                $affectedProducts[$variant->product->id] = $variant->product;
                            }
                        }
                    }

                    if (!empty($productRestitutions)) {
                        $pIds = array_keys($productRestitutions);
                        sort($pIds);
                        $products = \App\Models\Product::whereIn('id', $pIds)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                        foreach ($products as $prod) {
                            if ($prod->track_inventory) {
                                $prod->increment('quantity', $productRestitutions[$prod->id]);
                            }
                            $affectedProducts[$prod->id] = $prod;
                        }
                    }

                    foreach ($affectedProducts as $prod) {
                        $prod->syncWithVariants();
                        $prod->clearStorefrontCache();
                    }

                    // استرجاع مخزون الخدمات الإضافية المتتبعة بالاعتماد على عمود quantity المحفوظ بالطلب
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
                        sort($addonIds);
                        $trackedAddons = \App\Models\ProductAddon::whereIn('id', $addonIds)
                            ->where('track_inventory', true)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                        foreach ($trackedAddons as $tAddon) {
                            $tAddon->increment('quantity', $totalsByAddonId[$tAddon->id]);
                        }
                    }
                } else {
                    // إذا كان أونلاين غير مدفوع، نلغي أي حجز مؤقت متبقي فقط لمنع تضخم المخزون
                    \App\Models\StockReservation::where('order_id', $order->id)->delete();
                }
            }

            // إعادة استهلاك الكوبون إذا تم إرجاع الطلب من ملغي/مسترد إلى حالة نشطة
            if (in_array($oldStatus, ['cancelled', 'refunded']) && !in_array($newStatus, ['cancelled', 'refunded'])) {
                if ($order->coupon_id) {
                    $coupon = \App\Models\Coupon::find($order->coupon_id);
                    if (!$coupon) {
                        throw ValidationException::withMessages([
                            'status' => 'لا يمكن إعادة تفعيل هذا الطلب لأن كود الخصم المرتبط به لم يعد موجوداً في النظام.'
                        ]);
                    }

                    // قفل وإعادة حجز ذري مشروط
                    $incremented = \App\Models\Coupon::where('id', $coupon->id)
                        ->where(function ($q) {
                            $q->whereNull('usage_limit')
                              ->orWhereColumn('times_used', '<', 'usage_limit');
                        })
                        ->increment('times_used');

                    if (!$incremented) {
                        throw ValidationException::withMessages([
                            'status' => "لا يمكن إعادة تفعيل هذا الطلب لأن كود الخصم ({$coupon->code}) قد استنفد حده الأقصى من الاستخدامات بعد إلغاء الطلب."
                        ]);
                    }

                    // إعادة إنشاء سجل الاستخدام
                    \App\Models\CouponUsage::firstOrCreate([
                        'order_id' => $order->id,
                    ], [
                        'coupon_id' => $coupon->id,
                        'customer_id' => $order->customer_id,
                        'customer_phone' => \App\Models\Coupon::normalizePhone($order->customer_phone),
                        'discount_amount' => $order->discount_amount ?? 0,
                    ]);
                }
            }

            // توليد بوليصة الشحن عند تحول الطلب إلى جاهز للشحن (إذا لم تكن قد ولدت من قبل)
            if ($newStatus === 'ready_for_shipping' && $oldStatus !== 'ready_for_shipping') {
                if (empty($order->tracking_number)) {
                    \App\Jobs\CreateShippingWaybillJob::dispatch($order->id)->afterCommit();
                }
            }

            // إغلاق الدورة المالية للطلب عند التسليم الفعلي للعميل
            $orderUpdates = ['status' => $newStatus];
            if (in_array($newStatus, ['delivered', 'completed']) && !in_array($oldStatus, ['delivered', 'completed'])) {
                if ($order->payment_status === 'partially_paid' || ($order->payment_method === 'cod' && $order->payment_status !== 'paid')) {
                    $orderUpdates['payment_status'] = 'paid';
                    $orderUpdates['remaining_paid_at'] = now();
                }
            }

            $order->update($orderUpdates);

            return response()->json([
                'message' => 'Status updated successfully',
                'order' => $order
            ]);
        });
    }

    public function updateItemFileStatus(Request $request, $itemId)
    {
        $request->validate([
            'file_status' => 'required|in:pending,approved,rejected',
            'file_rejection_reason' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($itemId, $request) {
            $orderItem = OrderItem::with('order')->lockForUpdate()->findOrFail($itemId);
            
            $orderItem->update([
                'file_status' => $request->file_status,
                'file_rejection_reason' => $request->file_status === 'rejected' ? $request->file_rejection_reason : null,
            ]);

            // Trigger Email/Notification if rejected
            if ($request->file_status === 'rejected') {
                $email = $orderItem->order->customer_email ?? 'customer@example.com'; // Fallback if no email
                Notification::route('mail', $email)
                    ->notify(new FileRejectedNotification($orderItem, $request->file_rejection_reason));
            }

            return response()->json([
                'message' => 'File status updated successfully',
                'item' => $orderItem
            ]);
        });
    }

    public function downloadPrintFile(Request $request, $itemId, $category)
    {
        $orderItem = OrderItem::with('order')->findOrFail($itemId);

        if ($orderItem->item_type !== 'printing') {
            abort(400, 'هذا العنصر ليس طلب طباعة.');
        }

        $uploadedFiles = is_array($orderItem->uploaded_files) 
            ? $orderItem->uploaded_files 
            : (json_decode($orderItem->uploaded_files ?? '[]', true) ?: []);

        if (!isset($uploadedFiles[$category]) || !is_string($uploadedFiles[$category]) || empty($uploadedFiles[$category])) {
            abort(404, 'الملف المطلوب غير موجود في هذا الطلب.');
        }

        $path = $uploadedFiles[$category];

        // الحماية الصارمة من ثغرات Path Traversal
        if (str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            abort(403, 'مسار الملف غير مسموح به أمنياً.');
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'الملف غير موجود على القرص المحلي.');
        }

        $fileName = basename($path);
        return Storage::disk('local')->download($path, $fileName);
    }

    /**
     * Confirm / Adjust price for a specific order item.
     */
    public function confirmItemPrice(Request $request, $orderId, $itemId)
    {
        $validated = $request->validate([
            'confirmed_line_total' => 'required|numeric|min:0',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $cleanId = preg_replace('/[^0-9]/', '', (string)$orderId);
        $order = Order::findOrFail(!empty($cleanId) ? (int)$cleanId : $orderId);
        $item = $order->items()->findOrFail($itemId);

        // Session lock guard: prevent modification if customer is in an active payment gateway session
        if ($order->payment_session_expires_at && now()->lt($order->payment_session_expires_at) && $order->payment_status === 'unpaid') {
            throw ValidationException::withMessages([
                'order' => 'العميل متواجد في جلسة دفع نشطة حالياً. لا يمكن تعديل السعر حتى انتهاء الجلسة لمنع التضارب.'
            ]);
        }

        $updatedItem = OrderPricingService::confirmItemPrice(
            $item, 
            (float)$validated['confirmed_line_total'], 
            $validated['admin_notes'] ?? null, 
            auth()->id()
        );

        return response()->json([
            'message' => 'تم تحديث واعتماد سعر البند وإعادة احتساب الفاتورة بنجاح.',
            'item' => $updatedItem,
            'order' => $order->fresh(['items.addons'])
        ]);
    }

    /**
     * Issue confirmed quote token and send payment link.
     */
    public function issuePriceConfirmation(Request $request, $orderId)
    {
        $validated = $request->validate([
            'hours_valid' => 'nullable|integer|min:1|max:168',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $cleanId = preg_replace('/[^0-9]/', '', (string)$orderId);
        $order = Order::with('items')->findOrFail(!empty($cleanId) ? (int)$cleanId : $orderId);

        $token = OrderPricingService::issuePriceConfirmation(
            $order, 
            (int)($validated['hours_valid'] ?? 24), 
            $validated['admin_notes'] ?? null
        );

        $paymentUrl = url('/checkout/pay/' . $token);

        return response()->json([
            'message' => 'تم إصدار اعتماد التسعيرة بنجاح وتوليد رابط الدفع المباشر.',
            'payment_token' => $token,
            'payment_url' => $paymentUrl,
            'expires_at' => $order->price_expires_at?->toIso8601String(),
            'order' => $order->fresh()
        ]);
    }

    /**
     * Confirm direct manual payment (InstaPay / Vodafone Cash / Bank Transfer).
     */
    public function confirmManualPayment(Request $request, $orderId)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string|in:instapay,vodafone_cash,manual,bank_transfer',
            'transaction_reference' => 'nullable|string|max:100',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $cleanId = preg_replace('/[^0-9]/', '', (string)$orderId);
        $order = Order::with('items')->findOrFail(!empty($cleanId) ? (int)$cleanId : $orderId);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'الطلب مدفوع بالفعل مسبقاً.'], 400);
        }

        DB::transaction(function () use ($order, $validated) {
            $methodName = match($validated['payment_method']) {
                'instapay' => 'InstaPay (إنستاباي)',
                'vodafone_cash' => 'Vodafone Cash (فودافون كاش)',
                'bank_transfer' => 'تحويل بنكي مباشر',
                default => 'تحويل يدوي',
            };

            $ref = !empty($validated['transaction_reference']) ? " | مرجع: {$validated['transaction_reference']}" : '';
            $notes = !empty($validated['admin_notes']) ? " | {$validated['admin_notes']}" : '';

            $order->payment_status = 'paid';
            $order->payment_method = $validated['payment_method'];
            $order->status = 'processing';
            $order->admin_price_notes = trim(($order->admin_price_notes ?? '') . " [تم تأكيد استلام السداد يدوياً عبر {$methodName}{$ref}{$notes}]");
            $order->save();

            // Deduct real inventory safely and idempotently
            app(\App\Services\Inventory\InventoryService::class)->deductOrderInventory($order);

            // Release any temporary stock reservations and ensure order is secured
            \App\Models\StockReservation::where('order_id', $order->id)->delete();

            // Automatically dispatch shipping waybill for confirmed order
            \App\Jobs\CreateShippingWaybillJob::dispatch($order->id)->afterCommit();
        });

        return response()->json([
            'message' => 'تم تأكيد استلام الدفع بنجاح وتحويل الطلب إلى قيد التنفيذ وإصدار بوليصة الشحن.',
            'order' => $order->fresh()
        ]);
    }
}
