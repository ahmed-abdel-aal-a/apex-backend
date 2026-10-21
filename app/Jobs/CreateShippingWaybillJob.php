<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CreateShippingWaybillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 120, 300];

    public $orderId;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (!$order) {
            Log::error("CreateShippingWaybillJob: Order {$this->orderId} not found.");
            return;
        }

        // Guard 1: Abort if order is pending price confirmation or cancelled/refunded
        if (in_array($order->status, ['pending_price_confirmation', 'cancelled', 'refunded'])) {
            Log::warning("CreateShippingWaybillJob: Order {$this->orderId} is in status {$order->status}. Aborting waybill generation.");
            return;
        }

        // Guard 2: Only process paid online orders, partially paid (deposit) orders, or COD orders
        $allowedPaymentStatuses = ['paid', 'partially_paid'];
        if ($order->payment_method !== 'cod' && !in_array($order->payment_status, $allowedPaymentStatuses)) {
            Log::warning("CreateShippingWaybillJob: Order {$this->orderId} is neither paid, partially paid, nor COD. Skipping.");
            return;
        }

        // Guard 3: Prevent duplicate waybills
        if (!empty($order->tracking_number)) {
            Log::info("CreateShippingWaybillJob: Order {$this->orderId} already has a tracking number {$order->tracking_number}.");
            return;
        }

        try {
            $settings = \App\Models\Setting::getAllSettings();
            $method = $order->shipping_method ?? 'standard';
            $companyCode = ($method === 'express')
                ? ($settings['express_shipping_company'] ?? null)
                : ($settings['standard_shipping_company'] ?? null);

            $company = \App\Models\ShippingCompany::where('code', $companyCode)
                ->where('is_active', true)
                ->first();

            $shippingCompany = $company ? $company->name : 'Apex Logistics';
            $trackingNumber = 'AWB-' . strtoupper(uniqid());

            // Calculate collectible COD amount for the courier
            $collectibleAmount = 0.00;
            if ($order->payment_status === 'paid') {
                $collectibleAmount = 0.00;
            } elseif ($order->payment_status === 'partially_paid') {
                $collectibleAmount = (float)($order->remaining_amount ?? 0.00);
            } elseif ($order->payment_method === 'cod') {
                $collectibleAmount = (float)($order->remaining_amount > 0 ? $order->remaining_amount : $order->total);
            }

            DB::transaction(function () use ($order, $trackingNumber, $shippingCompany) {
                $order->tracking_number = $trackingNumber;
                $order->shipping_company = $shippingCompany;
                // Move to ready_for_pickup when waybill is generated, not shipped until physical handover
                if ($order->status === 'ready_for_shipping' || $order->status === 'processing') {
                    $order->status = 'ready_for_pickup';
                }
                $order->save();
            });

            Log::info("CreateShippingWaybillJob: Successfully created waybill {$trackingNumber} via {$shippingCompany} for Order {$order->id} (Collectible COD: {$collectibleAmount} EGP)");
        } catch (\Exception $e) {
            Log::error("CreateShippingWaybillJob: Failed for Order {$order->id}. Error: " . $e->getMessage());
            // Retry logic could be added here by throwing the exception
            throw $e;
        }
    }
}
