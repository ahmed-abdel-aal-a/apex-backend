<?php

namespace App\Services\Shipping;

use App\Models\Order;

interface ShippingProviderInterface
{
    /**
     * Calculate the shipping cost dynamically via API.
     * Should throw an exception on failure or return null to trigger static fallback.
     * 
     * @param Order $order
     * @return float|null
     */
    public function calculateCost(Order $order): ?float;

    /**
     * Create a shipping waybill for the order.
     * 
     * @param Order $order
     * @return array Contains tracking_number and waybill_url
     */
    public function createWaybill(Order $order): array;

    /**
     * Track a shipment by its tracking number.
     * 
     * @param string $trackingNumber
     * @return string Current status
     */
    public function trackShipment(string $trackingNumber): string;

    /**
     * Parse webhook payload and standardize status.
     * 
     * @param array $payload
     * @return array ['tracking_number' => string, 'status' => string, 'timestamp' => Carbon]
     */
    public function parseWebhook(array $payload): array;
}
