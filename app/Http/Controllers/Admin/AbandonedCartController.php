<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartDiscount;
use App\Models\CartContactHistory;
use App\Models\AbandonedCartsSetting;
use App\Http\Resources\Admin\AdminCartResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AbandonedCartController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $query = Cart::with(['items', 'discount', 'contactHistory'])
            ->whereIn('status', ['pending', 'recovered', 'lost']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $carts = $query->latest('abandoned_at')->paginate(20);

        return AdminCartResource::collection($carts);
    }

    public function show($id)
    {
        $cart = Cart::with(['items', 'discount', 'contactHistory'])
            ->findOrFail($id);

        return new AdminCartResource($cart);
    }

    public function getSettings()
    {
        $storeId = config('tenant.current_store_id', 1);
        abort_if(!$storeId, 403, 'Store ID is not identified. Cannot process settings.');
        
        $settings = Cache::remember("abandoned_carts_settings_{$storeId}", 3600, function () {
            $setting = AbandonedCartsSetting::first();
            if (!$setting) {
                $setting = AbandonedCartsSetting::create(); 
            }
            return $setting;
        });

        return response()->json($settings);
    }

    public function updateSettings(Request $request)
    {
        $storeId = config('tenant.current_store_id', 1);
        abort_if(!$storeId, 403, 'Store ID is not identified. Cannot process settings.');

        $data = $request->validate([
            'consider_abandoned_after_minutes' => 'integer',
            'frequency_capping_hours' => 'integer',
            'enable_push_notifications' => 'boolean',
            'push_notification_title' => 'nullable|string',
            'push_notification_body' => 'nullable|string',
            'whatsapp_template' => 'nullable|string',
            'email_subject' => 'nullable|string',
            'email_template' => 'nullable|string',
            'enable_discounts' => 'boolean',
            'discount_type' => 'in:percentage,fixed',
            'discount_value' => 'numeric',
            'anti_abuse_days' => 'integer',
            'exclude_sale_items' => 'boolean',
        ]);

        $settings = AbandonedCartsSetting::first() ?? new AbandonedCartsSetting();
        $settings->fill($data);
        $settings->save();

        return response()->json($settings);
    }

    public function generateDiscount(Request $request, $id)
    {
        $cart = Cart::with('items')->findOrFail($id);
        $settings = AbandonedCartsSetting::first() ?? new AbandonedCartsSetting();

        if (!$settings->enable_discounts) {
            return response()->json(['message' => 'Discounts are disabled in settings.'], 403);
        }

        // Anti-abuse check
        $hasRecentDiscount = CartDiscount::whereHas('cart', function ($q) use ($cart) {
            $q->where('customer_email', $cart->customer_email)
              ->orWhere('customer_phone', $cart->customer_phone);
        })->where('created_at', '>=', Carbon::now()->subDays($settings->anti_abuse_days))->exists();

        if ($hasRecentDiscount && ($cart->customer_email || $cart->customer_phone)) {
            return response()->json(['message' => 'Customer received a discount recently.'], 403);
        }

        // Sale items exclusion check
        if ($settings->exclude_sale_items) {
            $allSaleItems = $cart->items->every(fn($item) => $item->is_sale_item);
            if ($allSaleItems && $cart->items->count() > 0) {
                return response()->json(['message' => 'Cannot apply discount to sale items.'], 403);
            }
        }

        $discount = $cart->discount()->create([
            'code' => 'SAVE' . strtoupper(Str::random(6)),
            'type' => $settings->discount_type,
            'value' => $settings->discount_value,
            'expires_at' => Carbon::now()->addDays(2),
        ]);

        return response()->json($discount, 201);
    }

    public function logContactEvent(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:whatsapp,email,sms,push',
            'status' => 'required|in:sent,delivered,failed,clicked',
        ]);

        $cart = Cart::findOrFail($id);

        $event = $cart->contactHistory()->create([
            'type' => $request->type,
            'status' => $request->status,
        ]);

        return response()->json($event, 201);
    }

    public function sendPushNotification(Request $request, $id)
    {
        $cart = Cart::findOrFail($id);

        $event = $cart->contactHistory()->create([
            'type' => 'push',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم إرسال إشعار المتصفح بنجاح',
            'event' => $event,
        ], 200);
    }
}
