<?php

namespace App\Services;

use App\Models\PrintingType;
use App\Models\PrintingVolumeDiscount;
use App\Models\PrintingAttributeValue;

class PrintPricingService
{
    /**
     * حساب السعر الشامل للمطبعة (Pricing Engine V14)
     *
     * @param int $printingTypeId
     * @param int $quantity
     * @param array $selectedOptions Array of ['value_id' => ID, 'multiplier_value' => int|null]
     * @param float|int|null $widthMm
     * @param float|int|null $lengthMm
     * @param int|null $imageCount
     * @return array
     */
    public function calculatePrice(int $printingTypeId, int $quantity, array $selectedOptions, float|int|null $widthMm = null, float|int|null $lengthMm = null, int $taxBasisPoints = 0, ?int $imageCount = 1): array
    {
        $type = PrintingType::with('attributes.values')->findOrFail($printingTypeId);
        
        $subtotal = '0';
        $areaM2 = '0';
        
        $sumFixed = '0';
        $sumUnit = '0';
        $sumMultiplier = '0';
        $sumWeightPhysical = '0'; // in grams
        $thicknessMultiplier = 1;
        
        $valueIds = collect($selectedOptions)->pluck('value_id')->filter()->unique()->toArray();
        $values = PrintingAttributeValue::whereIn('id', $valueIds)
            ->whereHas('attribute', function($q) use ($printingTypeId) {
                $q->where('printing_type_id', $printingTypeId);
            })->get()->keyBy('id');

        foreach ($selectedOptions as $option) {
            $value = $values->get($option['value_id'] ?? null);
            if (!$value) continue;
            
            $sumFixed = bcadd($sumFixed, (string)$value->fixed_price);
            $sumUnit = bcadd($sumUnit, (string)$value->unit_price);
            
            if (isset($option['multiplier_value'])) {
                $multiplierVal = (string)$option['multiplier_value'];
                if (bccomp($multiplierVal, '1', 4) < 0) {
                    $multiplierVal = '1';
                }
                $sumMultiplier = bcadd($sumMultiplier, bcmul((string)$value->multiplier_price, $multiplierVal, 4));
                if ((int)$multiplierVal > 0) {
                    $thicknessMultiplier = max($thicknessMultiplier, (int)$multiplierVal);
                }
            } else {
                $sumMultiplier = bcadd($sumMultiplier, (string)$value->multiplier_price);
            }
            
            if ($value->weight_type === 'per_unit') {
                $sumWeightPhysical = bcadd($sumWeightPhysical, bcmul((string)$value->weight, (string)$quantity, 4));
            }
        }

        $isImageCountPricing = $type->upload_rules['pricing_by_image_count'] ?? false;
        $effectiveImageCount = $isImageCountPricing ? max(1, $imageCount ?? 1) : 1;
        
        $pricingQty = bcmul((string)$quantity, (string)$effectiveImageCount);

        if ($type->pricing_method === 'fixed') {
            $subtotal = bcadd($subtotal, (string)$type->base_price_fixed);
            $subtotal = bcadd($subtotal, $sumFixed);
            $subtotal = bcadd($subtotal, bcmul($sumUnit, $pricingQty, 4));
            $subtotal = bcadd($subtotal, bcmul($sumMultiplier, $pricingQty, 4));
            
        } elseif ($type->pricing_method === 'attributes_only') {
            $subtotal = bcadd($subtotal, $sumFixed);
            $subtotal = bcadd($subtotal, bcmul($sumUnit, $pricingQty, 4));
            $subtotal = bcadd($subtotal, bcmul($sumMultiplier, $pricingQty, 4));
            
        } elseif ($type->pricing_method === 'area') {
            $width = $widthMm ?? 0;
            $length = $lengthMm ?? 0;
            $areaMm2 = (string)($width * $length);
            $effectiveAreaMm2 = bccomp($areaMm2, (string)$type->min_chargeable_area) > 0 ? $areaMm2 : (string)$type->min_chargeable_area;
            $areaM2 = bcdiv($effectiveAreaMm2, '1000000', 4);
            
            $subtotal = bcadd($subtotal, (string)$type->base_price_fixed);
            $subtotal = bcadd($subtotal, bcmul($sumUnit, $pricingQty, 4));
            
            $areaQty = bcmul($areaM2, $pricingQty, 4);
            $multiplierCost = bcmul($sumMultiplier, $areaQty, 4);
            
            $subtotal = bcadd($subtotal, $multiplierCost);
            
            foreach ($selectedOptions as $option) {
                $value = $values->get($option['value_id'] ?? null);
                if ($value && $value->weight_type === 'per_sqm') {
                    $sumWeightPhysical = bcadd($sumWeightPhysical, bcmul((string)$value->weight, $areaQty, 4));
                }
            }
        }
        
        if (bccomp((string)$type->min_chargeable_price, $subtotal) > 0) {
            $subtotal = (string)$type->min_chargeable_price;
        }
        
        $discountAmount = '0';
        $discount = PrintingVolumeDiscount::where('printing_type_id', $printingTypeId)
            ->where('min_qty', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('max_qty')
                  ->orWhere('max_qty', '>=', $quantity);
            })
            ->first();
            
        if ($discount) {
            $discountRaw = bcdiv(bcmul($subtotal, (string)$discount->discount_basis_points, 4), '10000', 4);
            $discountAmount = bcceil($discountRaw);
        }
        
        $afterDiscount = bcsub($subtotal, $discountAmount);
        
        $taxRaw = bcdiv(bcmul($afterDiscount, (string)$taxBasisPoints, 4), '10000', 4);
        $taxAmount = bcceil($taxRaw);
        
        $finalTotal = bcadd($afterDiscount, $taxAmount);
        
        $finalPriceEgp = bcdiv($finalTotal, '100', 2);
        
        $physicalWeightKg = bcdiv($sumWeightPhysical, '1000', 4);
        
        $volumetricWeightKg = '0';
        if ($type->thickness_factor > 0) {
            $width = $widthMm ?? 0;
            $length = $lengthMm ?? 0;
            $thickness = $type->thickness_factor;
            $thickness = $thickness * $thicknessMultiplier;
            
            $volumeMm3 = bcmul(bcmul((string)$width, (string)$length), (string)$thickness);
            $volumeQtyMm3 = bcmul($volumeMm3, (string)$quantity);
            
            $volumetricWeightKg = bcdiv($volumeQtyMm3, '5000000', 4);
        }
        
        $billableWeightKg = bccomp($physicalWeightKg, $volumetricWeightKg, 4) > 0 ? $physicalWeightKg : $volumetricWeightKg;

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'final_total' => $finalTotal,
            'final_price_egp' => $finalPriceEgp,
            'physical_weight_kg' => (float)$physicalWeightKg,
            'volumetric_weight_kg' => (float)$volumetricWeightKg,
            'billable_weight_kg' => (float)$billableWeightKg,
        ];
    }
}
