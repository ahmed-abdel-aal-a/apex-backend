<?php

namespace App\Services\Shipping;

use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Log;

class ShippingFactory
{
    /**
     * Get the active shipping provider based on order details or store settings.
     * 
     * @param Order $order
     * @return ShippingProviderInterface|null
     */
    public function getProvider(Order $order): ?ShippingProviderInterface
    {
        // For Phase 1, we will return null or resolve based on configuration.
        // E.g., if (setting('shipping_provider') === 'bosta') return new BostaProvider();
        return null; // By returning null, we force the fallback calculation.
    }

    /**
     * Calculate cost with static fallback.
     */
    public function calculateCostWithFallback(Order $order): array
    {
        $provider = $this->getProvider($order);
        
        if ($provider) {
            try {
                $cost = $provider->calculateCost($order);
                if ($cost !== null) {
                    return [
                        'cost' => $cost,
                        'is_fallback' => false
                    ];
                }
            } catch (Exception $e) {
                Log::error("Shipping API Calculation Failed: " . $e->getMessage());
                // Fallback to static
            }
        }

        // Static Fallback logic based on Shipping Zone
        $shippingZone = \App\Models\ShippingZone::find($order->shipping_zone_id);
        $cost = $shippingZone ? $shippingZone->cost : 0.0;

        return [
            'cost' => (float) $cost,
            'is_fallback' => true
        ];
    }
}
