<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    /**
     * Display a listing of coupons.
     */
    public function index(Request $request)
    {
        $query = Coupon::withCount('usages');

        if ($request->filled('search')) {
            $search = strtoupper(trim($request->search));
            $query->where('code', 'like', "%{$search}%");
        }

        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== 'all') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('type') && in_array($request->type, ['fixed', 'percentage'])) {
            $query->where('type', $request->type);
        }

        $coupons = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($coupons);
    }

    /**
     * Store a newly created coupon in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_user' => 'nullable|integer|min:1',
            'apply_to_sale_items' => 'boolean',
            'start_date' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validated['type'] === 'percentage' && (float)$validated['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => 'نسبة الخصم المئوية لا يمكن أن تتجاوز 100%.'
            ]);
        }

        $coupon = Coupon::create([
            'code' => $validated['code'],
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_order_amount' => $validated['min_order_amount'] ?? null,
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'usage_per_user' => $validated['usage_per_user'] ?? null,
            'apply_to_sale_items' => $validated['apply_to_sale_items'] ?? true,
            'start_date' => $validated['start_date'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'تم إنشاء كود الخصم بنجاح.',
            'coupon' => $coupon->loadCount('usages'),
        ], 201);
    }

    /**
     * Display the specified coupon.
     */
    public function show(Coupon $coupon)
    {
        $coupon->loadCount('usages')->load(['usages' => function ($q) {
            $q->latest()->take(20);
        }]);

        return response()->json($coupon);
    }

    /**
     * Update the specified coupon in storage.
     */
    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_user' => 'nullable|integer|min:1',
            'apply_to_sale_items' => 'boolean',
            'start_date' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validated['type'] === 'percentage' && (float)$validated['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => 'نسبة الخصم المئوية لا يمكن أن تتجاوز 100%.'
            ]);
        }

        $coupon->update([
            'code' => $validated['code'],
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_order_amount' => $validated['min_order_amount'] ?? null,
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'usage_per_user' => $validated['usage_per_user'] ?? null,
            'apply_to_sale_items' => $validated['apply_to_sale_items'] ?? true,
            'start_date' => $validated['start_date'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'تم تحديث كود الخصم بنجاح.',
            'coupon' => $coupon->loadCount('usages'),
        ]);
    }

    /**
     * Remove the specified coupon from storage.
     */
    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json([
            'message' => 'تم حذف كود الخصم بنجاح.'
        ]);
    }

    /**
     * Toggle coupon active state.
     */
    public function toggleActive(Coupon $coupon)
    {
        $coupon->update(['is_active' => !$coupon->is_active]);

        return response()->json([
            'message' => 'تم تغيير حالة الكود بنجاح.',
            'is_active' => $coupon->is_active,
        ]);
    }
}
