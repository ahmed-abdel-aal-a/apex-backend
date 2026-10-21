<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Get or create an anonymous cart for the session.
     */
    public function getOrCreateCart(?string $sessionId, ?int $storeId, ?string $ip, ?string $userAgent): Cart
    {
        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
        }

        $cart = Cart::where('session_id', $sessionId)
            ->whereIn('status', ['active', 'pending'])
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'uuid' => Str::uuid()->toString(),
                'session_id' => $sessionId,
                'store_id' => $storeId,
                'customer_type' => 'anonymous',
                'status' => 'active',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
        } else {
            // Update IP and User Agent on subsequent accesses to track the latest snapshot
            $cart->update([
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
        }

        return $cart;
    }

    /**
     * Capture contact details, snapshot them, and promote to Guest.
     */
    public function captureContact(Cart $cart, array $data): Cart
    {
        // 1. Snapshot Data in Cart
        $cart->customer_name = $data['name'] ?? $cart->customer_name;
        $cart->customer_email = $data['email'] ?? $cart->customer_email;
        $cart->customer_phone = $data['phone'] ?? $cart->customer_phone;
        
        if (isset($data['ip_address'])) $cart->ip_address = $data['ip_address'];
        if (isset($data['user_agent'])) $cart->user_agent = $data['user_agent'];

        // 2. Promote to Guest in Customers table
        if ($cart->customer_email || $cart->customer_phone) {
            $customer = $this->findOrCreateGuestCustomer(
                $cart->store_id,
                $cart->session_id,
                $cart->customer_name,
                $cart->customer_email,
                $cart->customer_phone,
                $cart->ip_address,
                $cart->user_agent
            );

            $cart->customer_id = $customer->id;
            
            // Only promote anonymous to guest, don't downgrade registered
            if ($cart->customer_type === 'anonymous') {
                $cart->customer_type = 'guest';
            }
        }

        $cart->save();

        return $cart;
    }

    protected function findOrCreateGuestCustomer($storeId, $sessionId, $name, $email, $phone, $ip, $userAgent)
    {
        $customer = null;

        // 1. Priority lookup by Email (Strongest Identifier)
        if ($email) {
            $customer = Customer::where('store_id', $storeId)->where('email', $email)->first();
        }

        // 2. Secondary lookup by Phone
        if (!$customer && $phone) {
            $customer = Customer::where('store_id', $storeId)->where('phone', $phone)->first();
        }

        // 3. Tertiary lookup by Session ID to link tracked data
        if (!$customer && $sessionId) {
            $customer = Customer::where('store_id', $storeId)->where('session_id', $sessionId)->first();
            
            // تحديث بيانات العميل المجهول ببيانات الـ Checkout الحقيقية لتفادي بقائه كشبح
            if ($customer) {
                $customer->update([
                    'email' => $email ?? $customer->email,
                    'phone' => $phone ?? $customer->phone,
                    'name'  => $name ?? $customer->name,
                    'is_guest' => true // إبقاؤه كزائر لأنه لم يقم بتعيين كلمة مرور
                ]);
            }
        }

        if (!$customer) {
            // Create completely new guest customer
            $customer = Customer::create([
                'store_id' => $storeId,
                'session_id' => $sessionId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
        } else {
            // Update session and security snapshot if customer already exists
            $updateData = [
                'session_id' => $sessionId,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ];

            // Only populate missing fields to prevent data corruption from mistyped inputs
            if (!$customer->email && $email) $updateData['email'] = $email;
            if (!$customer->phone && $phone) $updateData['phone'] = $phone;
            if (!$customer->name && $name) $updateData['name'] = $name;

            $customer->update($updateData);
        }

        return $customer;
    }

    /**
     * Calculate price for selected addons and lock inventory if needed.
     */
    public function calculateAddonsPrice(float $basePrice, int $itemQuantity, array $addonIds, &$subtotal, &$lockedAddons, &$snapshots)
    {
        $addons = $lockedAddons->whereIn('id', $addonIds);
        $reservationIds = [];
        
        foreach ($addons as $addon) {
            $addonPrice = 0;
            if ($addon->price_type === 'percentage') {
                $addonPrice = $basePrice * ($addon->price / 100);
            } else {
                $addonPrice = $addon->price;
            }

            $multiplier = $addon->group->price_multiplier_type ?? 'per_item';
            if ($multiplier === 'per_item') {
                $totalAddonPrice = $addonPrice * $itemQuantity;
            } else {
                $totalAddonPrice = $addonPrice;
            }

            $subtotal += $totalAddonPrice;
            $snapshots[] = [
                'product_addon_id' => $addon->id,
                'name' => $addon->name,
                'price_type' => $addon->price_type,
                'price_at_purchase' => $addonPrice,
                'total' => $totalAddonPrice,
                'multiplier_type' => $multiplier,
                'quantity' => $itemQuantity, // tracking quantity for per_item
            ];

            // Handle Addon Inventory
            if ($addon->track_inventory) {
                $qtyToDeduct = ($multiplier === 'per_item') ? $itemQuantity : 1;
                if ($addon->available_quantity < $qtyToDeduct) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'cart_items' => "الكمية المطلوبة من الخدمة الإضافية ({$addon->name['ar']}) غير متوفرة."
                    ]);
                }
                $res = \App\Models\StockReservation::create([
                    'product_addon_id' => $addon->id,
                    'session_id' => request()->header('X-Session-ID'),
                    'user_id' => auth()->id(),
                    'quantity' => $qtyToDeduct,
                    'expires_at' => now()->addMinutes(15)
                ]);
                $reservationIds[] = $res->id;
            }
        }

        return $reservationIds;
    }

    /**
     * Add a standard item to the cart.
     */
    public function addStandardItem(Cart $cart, array $data)
    {
        // Resolve the correct price from product/variant
        $product = \App\Models\Product::findOrFail($data['product_id']);
        $variantId = $data['variant_id'] ?? null;
        $isSaleItem = false;

        if ($variantId) {
            $variant = \App\Models\ProductVariant::findOrFail($variantId);
            if ($variant->discount_price !== null) {
                $priceToUse = $variant->discount_price;
                $isSaleItem = true;
            } else {
                $priceToUse = $variant->price;
            }
        } else {
            if ($product->discount_price !== null) {
                $priceToUse = $product->discount_price;
                $isSaleItem = true;
            } else {
                $priceToUse = $product->price;
            }
        }

        // Add to cart_items table
        $cartItem = \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $data['product_id'],
            'product_variant_id' => $variantId,
            'quantity' => $data['quantity'],
            'price' => (float) $priceToUse,
            'is_sale_item' => $isSaleItem,
        ]);

        // Process Addons
        if (!empty($data['addons'])) {
            foreach ($data['addons'] as $addon) {
                $productAddonModel = \App\Models\ProductAddon::find($addon['product_addon_id']);
                $validTempFileIds = [];
                
                $rawFileIds = !empty($addon['temp_file_ids'])
                    ? (array) $addon['temp_file_ids']
                    : (!empty($addon['temp_file_id']) ? [$addon['temp_file_id']] : []);

                // التحقق الإجباري: إذا كانت الخدمة تتطلب ملفات ولكن لم يتم إرسال أي ملف
                if ($productAddonModel && $productAddonModel->requires_upload && empty($rawFileIds)) {
                    $addonName = is_array($productAddonModel->name) ? ($productAddonModel->name['ar'] ?? reset($productAddonModel->name)) : $productAddonModel->name;
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'addons' => "الخدمة الإضافية ({$addonName}) تتطلب رفع ملف تصميم."
                    ]);
                }

                if (!empty($rawFileIds)) {
                    if (!$productAddonModel || !$productAddonModel->requires_upload) {
                        $addonName = is_array($productAddonModel?->name) ? ($productAddonModel->name['ar'] ?? reset($productAddonModel->name)) : ($productAddonModel?->name ?? '');
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'addons' => "الخدمة الإضافية ({$addonName}) لا تتطلب أو تدعم رفع ملفات."
                        ]);
                    }

                    $maxAllowed = $productAddonModel->allow_multiple_uploads ? ($productAddonModel->max_uploads ?? 1) : 1;
                    if (count($rawFileIds) > $maxAllowed) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'addons' => "عدد الملفات المرفوعة يتجاوز الحد المسموح به وهو {$maxAllowed}."
                        ]);
                    }

                    foreach ($rawFileIds as $tempId) {
                        $tempUpload = \App\Models\TemporaryUpload::where('id', $tempId)
                            ->where(function($query) use ($cart) {
                                $query->where('session_id', $cart->session_id)
                                      ->orWhere('user_id', auth('sanctum')->id());
                            })->first();

                        if (!$tempUpload) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'addons' => "أحد الملفات المرفوعة غير صالح."
                            ]);
                        }

                        $uploadDisk = $tempUpload->disk ?? config('filesystems.default');
                        if (!\Illuminate\Support\Facades\Storage::disk($uploadDisk)->exists($tempUpload->file_path)) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'addons' => "الملف المرفوع لم يعد موجوداً على الخادم، يرجى إعادة الرفع."
                            ]);
                        }

                        $validTempFileIds[] = $tempId;
                    }
                }

                \App\Models\CartItemAddon::create([
                    'cart_item_id' => $cartItem->id,
                    'product_addon_id' => $addon['product_addon_id'],
                    'disk' => config('filesystems.default'),
                    'temp_file_ids' => empty($validTempFileIds) ? null : $validTempFileIds,
                ]);
            }
        }

        $this->syncAndValidateCoupon($cart);

        return $cartItem;
    }

    public function updateItemQuantity(\App\Models\CartItem $cartItem, int $quantity)
    {
        if ($cartItem->item_type === 'printing') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quantity' => 'لا يمكن تعديل كمية منتجات الطباعة من السلة، يرجى إعادة التكوين من صفحة الخدمة.'
            ]);
        }
        
        $cartItem->update(['quantity' => $quantity]);
        
        if ($cartItem->cart) {
            $this->syncAndValidateCoupon($cartItem->cart);
        }

        return $cartItem;
    }

    public function migrateLegacyCart(Cart $cart, array $items)
    {
        foreach ($items as $itemData) {
            // Find existing matching standard product
            $existingItem = \App\Models\CartItem::where('cart_id', $cart->id)
                ->where('product_id', $itemData['product_id'])
                ->where('product_variant_id', $itemData['variant_id'] ?? null)
                ->whereNull('printing_type_id') // Ensure it's a standard product
                ->first();

            // For simplicity in migration and to prevent losing addon selections,
            // we only merge quantities if BOTH items have zero addons.
            // If they have addons, we insert as a separate line item.
            
            if ($existingItem && empty($itemData['addons'])) {
                // Check if existing item also has no addons
                if ($existingItem->addons()->count() === 0) {
                    // Merge Conflict Resolution: Sum quantities
                    $existingItem->update([
                        'quantity' => $existingItem->quantity + $itemData['quantity']
                    ]);
                    continue;
                }
            }

            // Otherwise, create a new item
            $this->addStandardItem($cart, $itemData);
        }

        $this->syncAndValidateCoupon($cart);
    }

    /**
     * Re-calculate and validate coupon eligibility on the cart.
     * If the coupon is no longer valid, it is cleanly detached.
     * Returns an error message if invalidated, or null if valid / no coupon.
     */
    public function syncAndValidateCoupon(Cart $cart): ?string
    {
        if (!$cart->coupon_id) {
            if ((float)$cart->discount_amount > 0) {
                $cart->updateQuietly(['discount_amount' => 0.00]);
            }
            return null;
        }

        $cart->load(['items.product', 'items.productVariant', 'coupon']);
        $coupon = $cart->coupon;

        if (!$coupon) {
            $cart->updateQuietly([
                'coupon_id' => null,
                'discount_amount' => 0.00,
            ]);
            return 'كود الخصم لم يعد متاحاً.';
        }

        // Calculate eligible subtotal based on apply_to_sale_items
        $eligibleSubtotal = 0.00;
        foreach ($cart->items as $item) {
            $itemTotal = (float)$item->price * (int)$item->quantity;
            if ($coupon->apply_to_sale_items || !$item->is_sale_item) {
                $eligibleSubtotal += $itemTotal;
            }
        }

        try {
            $coupon->validateEligibility($eligibleSubtotal, $cart->customer_phone, $cart->customer_id);
            $newDiscount = $coupon->calculateDiscount($eligibleSubtotal);
            $cart->updateQuietly(['discount_amount' => $newDiscount]);
            return null;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $cart->updateQuietly([
                'coupon_id' => null,
                'discount_amount' => 0.00,
            ]);
            return $e->validator->errors()->first('coupon') ?? 'تم إلغاء كود الخصم لعدم استيفاء الشروط.';
        }
    }
}
