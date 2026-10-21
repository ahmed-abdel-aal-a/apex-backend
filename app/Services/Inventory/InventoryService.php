<?php

namespace App\Services\Inventory;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductAddon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * Deduct inventory for an order idempotently.
     * Prevents double deduction using inventory_deducted_at timestamp.
     */
    public function deductOrderInventory(Order $order): bool
    {
        // 1. Idempotency Guard: Do not deduct if already deducted
        if ($order->inventory_deducted_at !== null) {
            Log::info("Inventory already deducted for Order #{$order->id} at {$order->inventory_deducted_at}. Skipping.");
            return true;
        }

        $order->loadMissing(['items.addons']);

        DB::transaction(function () use ($order) {
            // Re-fetch order with lock inside transaction to prevent race conditions
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            if ($lockedOrder->inventory_deducted_at !== null) {
                return;
            }

            $affectedProducts = [];
            $variantDeductions = [];
            $productDeductions = [];
            $totalsByAddonId = [];

            foreach ($lockedOrder->items as $orderItem) {
                if ($orderItem->item_type === 'standard') {
                    if ($orderItem->variant_id) {
                        $variantDeductions[$orderItem->variant_id] = ($variantDeductions[$orderItem->variant_id] ?? 0) + $orderItem->quantity;
                    } elseif ($orderItem->product_id) {
                        $productDeductions[$orderItem->product_id] = ($productDeductions[$orderItem->product_id] ?? 0) + $orderItem->quantity;
                    }

                    if ($orderItem->relationLoaded('addons') && $orderItem->addons) {
                        foreach ($orderItem->addons as $itemAddon) {
                            if ($itemAddon->product_addon_id) {
                                $totalsByAddonId[$itemAddon->product_addon_id] = ($totalsByAddonId[$itemAddon->product_addon_id] ?? 0) + $itemAddon->quantity;
                            }
                        }
                    }
                }
            }

            // Deduct variant stock
            if (!empty($variantDeductions)) {
                $vIds = array_keys($variantDeductions);
                sort($vIds);
                $variants = ProductVariant::with('product')
                    ->whereIn('id', $vIds)
                    ->lockForUpdate()
                    ->get();

                foreach ($variants as $variant) {
                    $qtyToDeduct = $variantDeductions[$variant->id];
                    if ($variant->track_inventory) {
                        $variant->decrement('quantity', min($variant->quantity, $qtyToDeduct));
                    }
                    if ($variant->product) {
                        $variant->product->syncWithVariants();
                        $affectedProducts[$variant->product->id] = $variant->product;
                    }
                }
            }

            // Deduct standard products stock
            if (!empty($productDeductions)) {
                $pIds = array_keys($productDeductions);
                sort($pIds);
                $products = Product::whereIn('id', $pIds)
                    ->lockForUpdate()
                    ->get();

                foreach ($products as $prod) {
                    $qtyToDeduct = $productDeductions[$prod->id];
                    if ($prod->track_inventory) {
                        $prod->decrement('quantity', min($prod->quantity, $qtyToDeduct));
                    }
                    $affectedProducts[$prod->id] = $prod;
                }
            }

            // Clear storefront cache for affected products
            foreach ($affectedProducts as $prod) {
                $prod->clearStorefrontCache();
            }

            // Deduct tracked addons
            if (!empty($totalsByAddonId)) {
                $addonIds = array_keys($totalsByAddonId);
                sort($addonIds);
                $trackedAddons = ProductAddon::whereIn('id', $addonIds)
                    ->where('track_inventory', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($trackedAddons as $tAddon) {
                    $requiredQty = $totalsByAddonId[$tAddon->id];
                    $tAddon->decrement('quantity', min($tAddon->quantity, $requiredQty));
                }
            }

            // Mark order inventory as deducted
            $now = now();
            $lockedOrder->inventory_deducted_at = $now;
            $lockedOrder->save();

            $order->inventory_deducted_at = $now;

            Log::info("Successfully deducted inventory for Order #{$order->id} at {$now}.");
        });

        return true;
    }
}
