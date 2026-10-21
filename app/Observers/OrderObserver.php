<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $this->updateCustomerTotals($order, 1, $order->total_amount ?? 0);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // If an order's total changes or it's completed, logic goes here
        // For simple increment, we only watch created/deleted unless we track status.
        if ($order->wasChanged('total_amount')) {
            $diff = $order->total_amount - $order->getOriginal('total_amount');
            $this->updateCustomerTotals($order, 0, $diff);
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $this->updateCustomerTotals($order, -1, -($order->total_amount ?? 0));
    }

    /**
     * Safely update the customer's total orders and total spent using DB::transaction.
     */
    protected function updateCustomerTotals(Order $order, int $orderCountChange, float $spentChange): void
    {
        if (!$order->customer_id) {
            return;
        }

        try {
            DB::transaction(function () use ($order, $orderCountChange, $spentChange) {
                // Lock the customer row for update to prevent race conditions
                $customer = Customer::lockForUpdate()->find($order->customer_id);
                
                if ($customer) {
                    $customer->total_orders += $orderCountChange;
                    $customer->total_spent += $spentChange;
                    
                    // Prevent negative values
                    $customer->total_orders = max(0, $customer->total_orders);
                    $customer->total_spent = max(0.00, $customer->total_spent);
                    
                    $customer->save();
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to update customer totals in OrderObserver: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id
            ]);
        }
    }
}
