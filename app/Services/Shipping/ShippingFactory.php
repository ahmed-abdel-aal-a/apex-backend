<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Setting;
use Exception;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class ShippingFactory
{
    /**
     * الحصول على مزود خدمة الشحن المناسب للطلب بناءً على إعدادات المتجر ونوع الشحن
     *
     * @param Order $order
     * @return ShippingProviderInterface
     */
    public function getProvider(Order $order): ShippingProviderInterface
    {
        $settings = Setting::getAllSettings();
        $method = $order->shipping_method ?? 'standard';
        $companyCode = ($method === 'express')
            ? ($settings['express_shipping_company'] ?? 'bosta')
            : ($settings['standard_shipping_company'] ?? 'bosta');

        return $this->resolveProviderByName((string)$companyCode);
    }

    /**
     * حل وتجهيز كائن المزود بناءً على الكود النصي
     *
     * @param string $providerName
     * @return ShippingProviderInterface
     * @throws InvalidArgumentException
     */
    public function resolveProviderByName(string $providerName): ShippingProviderInterface
    {
        $code = strtolower(trim($providerName));

        if ($code === 'bosta') {
            return app(BostaShippingProvider::class);
        }

        throw new InvalidArgumentException("مزود خدمة الشحن '{$providerName}' غير مدعوم حالياً في النظام.");
    }

    /**
     * احتساب تكلفة الشحن مع التراجع لحساب المناطق الثابتة
     */
    public function calculateCostWithFallback(Order $order): array
    {
        try {
            $provider = $this->getProvider($order);
            $cost = $provider->calculateCost($order);
            if ($cost !== null) {
                return [
                    'cost' => $cost,
                    'is_fallback' => false
                ];
            }
        } catch (Exception $e) {
            Log::warning("Shipping API Calculation Failed: " . $e->getMessage() . ". Falling back to zone static rates.");
        }

        // Static Fallback logic based on Shipping Zone
        $zoneId = $order->zone_id;
        $shippingZone = $zoneId ? \App\Models\ShippingZone::find($zoneId) : null;
        $cost = $shippingZone ? (float)$shippingZone->rate : (float)($order->shipping_cost ?? 0.0);

        return [
            'cost' => (float)$cost,
            'is_fallback' => true
        ];
    }
}
