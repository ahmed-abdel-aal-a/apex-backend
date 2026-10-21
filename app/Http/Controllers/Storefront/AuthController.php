<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Storefront\CustomerRegisterRequest;
use App\Http\Requests\Storefront\CustomerLoginRequest;
use App\Http\Resources\Storefront\CustomerResource;

class AuthController extends Controller
{
    public function register(CustomerRegisterRequest $request)
    {
        $validated = $request->validated();

        $customer = Customer::where('email', $validated['email'])->first();

        if ($customer) {
            // Guest Merge Logic: ترقية الزائر المجهول إلى مستخدم مسجل
            if ($customer->is_guest && is_null($customer->password)) {
                $customer->update([
                    'name' => $validated['name'],
                    'password' => Hash::make($validated['password']),
                    'is_guest' => false,
                ]);
            } else {
                throw ValidationException::withMessages([
                    'email' => [__('The email has already been taken. Please log in.')],
                ]);
            }
        } else {
            // Create new registered customer
            $customer = Customer::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_guest' => false,
            ]);
        }

        $token = $customer->createToken('storefront_token')->plainTextToken;

        // دمج جلسة الزائر المجهول وسجلاته وسلاته تلقائياً في حساب العميل (Zero Duplicates Identity Stitching)
        $sessionId = $request->header('X-Session-ID');
        if ($sessionId) {
            Customer::mergeGuestSession($customer, $sessionId);
        }

        return response()->json([
            'customer' => new CustomerResource($customer),
            'token' => $token,
        ]);
    }

    public function login(CustomerLoginRequest $request)
    {
        $validated = $request->validated();

        $customer = Customer::where('email', $validated['email'])->where('is_guest', false)->first();

        if (!$customer || !Hash::check($validated['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $customer->createToken('storefront_token')->plainTextToken;

        // دمج جلسة الزائر المجهول وسجلاته وسلاته تلقائياً في حساب العميل عند تسجيل الدخول
        $sessionId = $request->header('X-Session-ID');
        if ($sessionId) {
            Customer::mergeGuestSession($customer, $sessionId);
        }

        return response()->json([
            'customer' => new CustomerResource($customer),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request)
    {
        return new CustomerResource($request->user());
    }

    public function orders(Request $request)
    {
        $customer = $request->user();

        $orders = \App\Models\Order::query()
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id);
                if (!empty($customer->phone)) {
                    $query->orWhere('customer_phone', $customer->phone);
                }
                if (!empty($customer->email)) {
                    $query->orWhere('customer_email', $customer->email);
                }
            })
            ->with(['items'])
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }
}

