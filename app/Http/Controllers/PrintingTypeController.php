<?php

namespace App\Http\Controllers;

use App\Models\PrintingType;
use Illuminate\Http\Request;

class PrintingTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = PrintingType::query();
        if ($request->has('printing_product_type_id')) {
            $query->where('printing_product_type_id', $request->printing_product_type_id);
        }
        $types = $query->orderBy('sort_order')->get();
        return response()->json($types);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'printing_product_type_id' => 'required|exists:printing_product_types,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string|url',
            'pricing_method' => 'required|in:fixed,attributes_only,area',
            'base_price_fixed' => 'integer|min:0',
            'base_price_unit' => 'integer|min:0',
            'base_price_multiplier' => 'integer|min:0',
            'min_quantity' => 'integer|min:1',
            'quantity_step' => 'integer|min:1',
            'min_chargeable_area' => 'integer|min:0',
            'min_chargeable_price' => 'integer|min:0',
            'is_active' => 'boolean',
            'requires_upload' => 'boolean',
            'upload_rules' => 'nullable|array',
            'upload_categories' => 'nullable|array',
            'standard_roll_sizes' => 'nullable|array',
            'max_print_width' => 'nullable|integer',
            'max_print_length' => 'nullable|integer',
            'bleed_margin' => 'integer|min:0',
            'safe_zone' => 'integer|min:0',
            'thickness_factor' => 'numeric|min:0',
            'base_production_days' => 'integer|min:1',
            'sort_order' => 'integer'
        ]);

        $type = PrintingType::create($validated);
        return response()->json($type, 201);
    }

    public function show(PrintingType $printingType)
    {
        return response()->json($printingType->load('attributes.values', 'volumeDiscounts'));
    }

    public function update(Request $request, PrintingType $printingType)
    {
        $validated = $request->validate([
            'printing_product_type_id' => 'sometimes|exists:printing_product_types,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string|url',
            'pricing_method' => 'sometimes|required|in:fixed,attributes_only,area',
            'base_price_fixed' => 'integer|min:0',
            'base_price_unit' => 'integer|min:0',
            'base_price_multiplier' => 'integer|min:0',
            'min_quantity' => 'integer|min:1',
            'quantity_step' => 'integer|min:1',
            'min_chargeable_area' => 'integer|min:0',
            'min_chargeable_price' => 'integer|min:0',
            'is_active' => 'boolean',
            'requires_upload' => 'boolean',
            'upload_rules' => 'nullable|array',
            'upload_categories' => 'nullable|array',
            'standard_roll_sizes' => 'nullable|array',
            'max_print_width' => 'nullable|integer',
            'max_print_length' => 'nullable|integer',
            'bleed_margin' => 'integer|min:0',
            'safe_zone' => 'integer|min:0',
            'thickness_factor' => 'numeric|min:0',
            'base_production_days' => 'integer|min:1',
            'sort_order' => 'integer'
        ]);

        $printingType->update($validated);
        return response()->json($printingType);
    }

    public function destroy(PrintingType $printingType)
    {
        $printingType->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
