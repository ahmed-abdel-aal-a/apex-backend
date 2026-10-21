<?php

namespace App\Services\Shipping;

use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\ShippingCompany;
use Illuminate\Validation\ValidationException;

class ShippingCalculationService
{
    /**
     * حساب تكاليف الشحن والضرائب والإجمالي بدقة BCMath الصارمة بنسبة 100%
     *
     * @param string $subtotal إجمالي المنتجات كـ string
     * @param string $discountAmount قيمة الخصم كـ string
     * @param int $zoneId معرف منطقة الشحن
     * @param string $shippingMethod 'standard' | 'express'
     * @param string $paymentMethod 'cod' | 'online' | etc.
     * @param string $taxAmount قيمة الضريبة المحسوبة كـ string (افتراضي '0.00')
     * @return array
     */
    public function calculate(
        string $subtotal,
        string $discountAmount,
        int $zoneId,
        string $shippingMethod = 'standard',
        string $paymentMethod = 'online',
        string $taxAmount = '0.00'
    ): array {
        $zone = ShippingZone::find($zoneId);
        if (!$zone) {
            throw ValidationException::withMessages([
                'zone_id' => 'منطقة الشحن المختارة غير موجودة بالنظام.'
            ]);
        }

        $settings = Setting::getAllSettings();

        // 1. حساب صافي المنتجات بعد الخصم
        $cleanSubtotal = (string)number_format((float)$subtotal, 2, '.', '');
        $cleanDiscount = (string)number_format((float)$discountAmount, 2, '.', '');
        $netItemsTotal = bcsub($cleanSubtotal, $cleanDiscount, 2);
        if (bccomp($netItemsTotal, '0.00', 2) < 0) {
            $netItemsTotal = '0.00';
        }

        // 2. إعدادات الشحن المجاني
        $freeShippingEnabled = filter_var($settings['free_shipping_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $freeShippingThreshold = (string)number_format((float)($settings['free_shipping_threshold'] ?? 0), 2, '.', '');

        $isFreeShipping = false;
        if ($freeShippingEnabled && bccomp($freeShippingThreshold, '0.00', 2) > 0) {
            if (bccomp($netItemsTotal, $freeShippingThreshold, 2) >= 0) {
                $isFreeShipping = true;
            }
        }

        // 3. الشحن الأساسي للمنطقة
        $baseZoneCost = (string)number_format((float)($zone->rate ?? $zone->cost ?? 0), 2, '.', '');
        $appliedBaseShipping = $isFreeShipping ? '0.00' : $baseZoneCost;

        // 4. الشحن السريع ورسومه الإضافية
        $expressEnabled = filter_var($settings['express_shipping_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $expressFee = (string)number_format((float)($settings['express_shipping_fee'] ?? 0), 2, '.', '');
        $expressDeliveryTime = $settings['express_shipping_delivery_time'] ?? 'خلال 24 - 48 ساعة';

        $resolvedMethod = ($shippingMethod === 'express' && $expressEnabled) ? 'express' : 'standard';
        $expressSurcharge = ($resolvedMethod === 'express') ? $expressFee : '0.00';

        // إجمالي تكلفة الشحن = الشحن الأساسي المطبق + رسوم الشحن السريع
        $finalShippingCost = bcadd($appliedBaseShipping, $expressSurcharge, 2);

        // 5. رسوم الدفع عند الاستلام (COD Fee)
        $codEnabled = filter_var($settings['cod_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $codFeeSetting = (string)number_format((float)($settings['cod_fee'] ?? 0), 2, '.', '');
        $appliedCodFee = ($paymentMethod === 'cod' && $codEnabled) ? $codFeeSetting : '0.00';

        // 6. التحقق من قيمة الضريبة كـ string آمن
        $sanitizedTax = (string)number_format((float)$taxAmount, 2, '.', '');

        // 7. حساب الإجمالي النهائي (Grand Total) بدقة bcadd الصارمة
        // أسعار المنتجات شاملة ضريبة القيمة المضافة، لذا الإجمالي = صافي المنتجات + الشحن النهائي + رسوم الدفع عند الاستلام
        $totalWithShipping = bcadd($netItemsTotal, $finalShippingCost, 2);
        $grandTotal = bcadd($totalWithShipping, $appliedCodFee, 2);

        // 8. تحديد الشركة المسندة حسب نوع الشحن مع التأكد من شرط التفعيل (is_active)
        $assignedCompanyCode = ($resolvedMethod === 'express')
            ? ($settings['express_shipping_company'] ?? null)
            : ($settings['standard_shipping_company'] ?? null);

        $assignedCompany = ShippingCompany::where('code', $assignedCompanyCode)
            ->where('is_active', true)
            ->first();

        return [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'delivery_days' => $zone->delivery_days,
            'base_shipping_cost' => $baseZoneCost,
            'is_free_shipping' => $isFreeShipping,
            'applied_base_shipping' => $appliedBaseShipping,
            'shipping_method' => $resolvedMethod,
            'standard_delivery_time' => $settings['standard_shipping_delivery_time'] ?? 'خلال 2 - 4 أيام عمل',
            'express_surcharge' => $expressSurcharge,
            'express_delivery_time' => $expressDeliveryTime,
            'final_shipping_cost' => $finalShippingCost,
            'cod_fee' => $appliedCodFee,
            'tax_amount' => $sanitizedTax,
            'net_items_total' => $netItemsTotal,
            'grand_total' => $grandTotal,
            'assigned_company_code' => $assignedCompanyCode,
            'assigned_company_name' => $assignedCompany?->name ?? 'Apex Logistics',
        ];
    }
}
