<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;

class CartRecoveryController extends Controller
{
    public function recover(Request $request, $uuid)
    {
        $cart = Cart::with('discount')->where('uuid', $uuid)->firstOrFail();

        // Check if cart is already recovered
        if ($cart->status === 'recovered') {
            return redirect('/cart')->with('message', 'This cart has already been recovered.');
        }

        // Logic to rehydrate session
        // Assuming we are storing the cart ID in the session
        session(['cart_id' => $cart->id]);

        // If a discount code was generated for this cart, apply it to the session
        if ($cart->discount && !$cart->discount->expires_at->isPast()) {
            session(['applied_discount_code' => $cart->discount->code]);
        }

        // Redirect to checkout
        return redirect('/checkout')->with('success', 'Cart successfully recovered.');
    }
}
