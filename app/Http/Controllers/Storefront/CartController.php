<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\CartService;
use App\Models\Cart;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Get the current cart for the session or create a new anonymous one.
     */
    public function getOrCreate(Request $request)
    {
        $sessionId = $request->header('X-Session-ID');
        $storeId = config('tenant.current_store_id') ?? $request->header('X-Store-ID');
        
        $cart = $this->cartService->getOrCreateCart(
            $sessionId,
            $storeId,
            $request->ip(),
            $request->userAgent()
        );

        $cart->load(['items.product', 'items.productVariant', 'items.printingType', 'items.addons.productAddon', 'coupon']);
        
        $missingFiles = false;
        foreach ($cart->items as $item) {
            if ($item->file_status === 'expired') {
                $missingFiles = true;
                break;
            }
        }

        return response()->json([
            'cart' => new \App\Http\Resources\Storefront\CartResource($cart),
            'session_id' => $cart->session_id,
            'missing_required_files' => $missingFiles,
        ]);
    }

    /**
     * Capture contact details to promote the cart to guest.
     */
    public function captureContact(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'name' => 'nullable|string',
        ]);

        if (!$request->email && !$request->phone) {
            return response()->json(['message' => 'Email or phone is required'], 422);
        }

        // The TenantScope will automatically scope this to the current store
        $cart = Cart::where('session_id', $request->session_id)
            ->whereIn('status', ['active', 'pending'])
            ->firstOrFail();

        $data = $request->only(['name', 'email', 'phone']);
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = $request->userAgent();

        $cart = $this->cartService->captureContact($cart, $data);

        return response()->json([
            'message' => 'Contact details captured successfully',
            'cart' => new \App\Http\Resources\Storefront\CartResource($cart->load(['items.product', 'items.productVariant', 'items.printingType', 'items.addons.productAddon', 'coupon']))
        ]);
    }

    public function addItem(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'addons' => 'nullable|array',
            'addons.*.product_addon_id' => 'required|exists:product_addons,id',
            'addons.*.temp_file_ids' => 'nullable|array',
            'addons.*.temp_file_ids.*' => 'string',
            'addons.*.temp_file_id' => 'nullable|string'
        ]);

        $sessionId = $request->header('X-Session-ID');
        if (!$sessionId) return response()->json(['message' => 'Session ID required'], 400);

        $cart = Cart::where('session_id', $sessionId)->firstOrFail();

        $cartItem = $this->cartService->addStandardItem($cart, $request->all());
        
        $customerId = $cart->customer_id;
        
        if (!$customerId && $cart->session_id) {
            $customer = \App\Models\Customer::firstOrCreate(
                ['store_id' => $cart->store_id, 'session_id' => $cart->session_id],
                [
                    'ip_address' => $cart->ip_address,
                    'user_agent' => $cart->user_agent,
                    'is_guest' => true,
                    'first_seen' => now(),
                    'last_seen' => now(),
                ]
            );
            $customerId = $customer->id;
            
            // ربط السلة بالعميل المجهول
            $cart->update(['customer_id' => $customerId]);
        }

        if ($customerId) {
            \App\Models\CustomerBrowsingHistory::create([
                'store_id' => $cart->store_id,
                'customer_id' => $customerId,
                'path' => $request->header('Referer', '/cart'),
                'title' => 'إضافة للسلة',
                'type' => 'add_to_cart',
                'visited_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Item added',
            'cart_item' => $cartItem->load('addons')
        ]);
    }

    public function updateItem(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $sessionId = $request->header('X-Session-ID');
        if (!$sessionId) return response()->json(['message' => 'Session ID required'], 400);

        $cart = Cart::where('session_id', $sessionId)->firstOrFail();
        $cartItem = \App\Models\CartItem::where('cart_id', $cart->id)->where('id', $itemId)->firstOrFail();

        $cartItem = $this->cartService->updateItemQuantity($cartItem, $request->quantity);
        $this->cartService->syncAndValidateCoupon($cart);

        return response()->json([
            'message' => 'Item updated',
            'cart_item' => $cartItem
        ]);
    }

    public function removeItem(Request $request, $itemId)
    {
        $sessionId = $request->header('X-Session-ID');
        if (!$sessionId) return response()->json(['message' => 'Session ID required'], 400);

        $cart = Cart::where('session_id', $sessionId)->firstOrFail();
        $cartItem = \App\Models\CartItem::where('cart_id', $cart->id)->where('id', $itemId)->firstOrFail();

        $cartItem->delete();
        $this->cartService->syncAndValidateCoupon($cart);

        return response()->json(['message' => 'Item removed']);
    }

    public function migrate(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.addons' => 'nullable|array',
        ]);

        $sessionId = $request->header('X-Session-ID');
        $storeId = config('tenant.current_store_id') ?? $request->header('X-Store-ID');
        
        $cart = $this->cartService->getOrCreateCart(
            $sessionId,
            $storeId,
            $request->ip(),
            $request->userAgent()
        );

        $this->cartService->migrateLegacyCart($cart, $request->items);

        return response()->json([
            'message' => 'Cart migrated',
            'cart' => new \App\Http\Resources\Storefront\CartResource($cart->load(['items.product', 'items.productVariant', 'items.printingType', 'items.addons.productAddon', 'coupon']))
        ]);
    }

    /**
     * Apply a coupon to the active session cart.
     */
    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $sessionId = $request->header('X-Session-ID');
        if (!$sessionId) {
            return response()->json(['message' => 'Session ID required'], 400);
        }

        $lockKey = 'cart_coupon_' . $sessionId;
        $lock = Cache::lock($lockKey, 5);

        if (!$lock->get()) {
            throw ValidationException::withMessages([
                'coupon' => 'جاري معالجة طلبك بالفعل، يرجى المحاولة بعد لحظات.'
            ]);
        }

        try {
            $cart = Cart::with(['items.product', 'items.productVariant', 'items.printingType', 'items.addons.productAddon'])
                ->where('session_id', $sessionId)
                ->whereIn('status', ['active', 'pending'])
                ->firstOrFail();

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'coupon' => 'السلة فارغة، يرجى إضافة منتجات أولاً لتطبيق كود الخصم.'
                ]);
            }

            $cleanCode = strtoupper(trim($request->code));
            $coupon = Coupon::where('code', $cleanCode)->first();

            if (!$coupon) {
                throw ValidationException::withMessages([
                    'coupon' => 'كود الخصم غير صحيح أو غير موجود.'
                ]);
            }

            // Calculate eligible subtotal based on apply_to_sale_items
            $eligibleSubtotal = 0.00;
            foreach ($cart->items as $item) {
                $itemTotal = (float)$item->price * (int)$item->quantity;
                if ($coupon->apply_to_sale_items || !$item->is_sale_item) {
                    $eligibleSubtotal += $itemTotal;
                }
            }

            $coupon->validateEligibility($eligibleSubtotal, $cart->customer_phone, $cart->customer_id);
            $discount = $coupon->calculateDiscount($eligibleSubtotal);

            // Single-slot replacement: cleanly update coupon_id and discount_amount
            $cart->update([
                'coupon_id' => $coupon->id,
                'discount_amount' => $discount,
            ]);

            $cart->load(['items.product', 'items.productVariant', 'items.printingType', 'items.addons.productAddon', 'coupon']);

            return response()->json([
                'message' => 'تم تطبيق كود الخصم بنجاح.',
                'cart' => new \App\Http\Resources\Storefront\CartResource($cart),
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Remove applied coupon from active session cart.
     */
    public function removeCoupon(Request $request)
    {
        $sessionId = $request->header('X-Session-ID');
        if (!$sessionId) {
            return response()->json(['message' => 'Session ID required'], 400);
        }

        $cart = Cart::where('session_id', $sessionId)
            ->whereIn('status', ['active', 'pending'])
            ->firstOrFail();

        $cart->update([
            'coupon_id' => null,
            'discount_amount' => 0.00,
        ]);

        $cart->load(['items.product', 'items.productVariant', 'items.printingType', 'items.addons.productAddon', 'coupon']);

        return response()->json([
            'message' => 'تم إزالة كود الخصم.',
            'cart' => new \App\Http\Resources\Storefront\CartResource($cart),
        ]);
    }
}
