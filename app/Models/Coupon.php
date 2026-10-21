<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_per_user',
        'times_used',
        'apply_to_sale_items',
        'start_date',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_per_user' => 'integer',
            'times_used' => 'integer',
            'apply_to_sale_items' => 'boolean',
            'is_active' => 'boolean',
            'start_date' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Mutator to ensure coupon code is always uppercase and trimmed.
     */
    protected function code(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn (?string $value) => $value !== null ? strtoupper(trim($value)) : null,
        );
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Standard Egyptian Phone Normalizer covering all standard patterns.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '00201') && strlen($clean) === 14) {
            return '0' . substr($clean, 4);
        }
        if (str_starts_with($clean, '201') && strlen($clean) === 12) {
            return '0' . substr($clean, 2);
        }
        if (str_starts_with($clean, '1') && strlen($clean) === 10) {
            return '0' . $clean;
        }
        return $clean;
    }

    /**
     * Validate coupon eligibility against eligible subtotal, dates, and customer limits.
     * Throws ValidationException if not valid.
     */
    public function validateEligibility(float $eligibleSubtotal, ?string $phone = null, int|string|null $customerId = null): void
    {
        $customerId = $customerId ? (int)$customerId : null;
        if (!$this->is_active) {
            throw ValidationException::withMessages(['coupon' => 'كود الخصم غير مفعّل حالياً.']);
        }

        $now = now();
        if ($this->start_date && $now->lt($this->start_date)) {
            throw ValidationException::withMessages(['coupon' => 'كود الخصم غير سارٍ بعد.']);
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            throw ValidationException::withMessages(['coupon' => 'عذراً، انتهت صلاحية كود الخصم هذا.']);
        }

        if ($this->usage_limit !== null && $this->times_used >= $this->usage_limit) {
            throw ValidationException::withMessages(['coupon' => 'عذراً، تم استنفاد الحد الأقصى لاستخدام هذا الكود.']);
        }

        if ($this->min_order_amount !== null && $eligibleSubtotal < (float)$this->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon' => sprintf(
                    'الحد الأدنى للطلب لتفعيل هذا الكود هو %s ج.م (المجموع المؤهل الحالي: %s ج.م).',
                    number_format((float)$this->min_order_amount, 2),
                    number_format($eligibleSubtotal, 2)
                )
            ]);
        }

        if ($eligibleSubtotal <= 0) {
            throw ValidationException::withMessages([
                'coupon' => 'لا توجد منتجات مؤهلة لتطبيق الخصم عليها في السلة.'
            ]);
        }

        // Check per-user usage limits
        $normalizedPhone = self::normalizePhone($phone);
        if ($this->usage_per_user !== null && ($normalizedPhone || $customerId)) {
            $userCount = $this->usages()->where(function ($q) use ($normalizedPhone, $customerId) {
                if ($customerId) {
                    $q->orWhere('customer_id', $customerId);
                }
                if ($normalizedPhone) {
                    $q->orWhere('customer_phone', $normalizedPhone);
                }
            })->count();

            if ($userCount >= $this->usage_per_user) {
                throw ValidationException::withMessages([
                    'coupon' => 'لقد قمت باستخدام هذا الكوبون بالحد الأقصى المسموح به لك سابقاً.'
                ]);
            }
        }
    }

    /**
     * Calculate discount amount mathematically clamped to eligible subtotal.
     */
    public function calculateDiscount(float $eligibleSubtotal): float
    {
        if ($eligibleSubtotal <= 0) {
            return 0.00;
        }

        if ($this->type === 'percentage') {
            $discount = $eligibleSubtotal * ((float)$this->value / 100);
            if ($this->max_discount_amount !== null) {
                $discount = min($discount, (float)$this->max_discount_amount);
            }
        } else {
            $discount = (float)$this->value;
        }

        // Clamp so discount never exceeds eligible subtotal
        $discount = min($discount, $eligibleSubtotal);

        return round(max(0.00, $discount), 2);
    }
}
