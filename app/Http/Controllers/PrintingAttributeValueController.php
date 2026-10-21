<?php

namespace App\Http\Controllers;

use App\Models\PrintingAttributeValue;
use Illuminate\Http\Request;

class PrintingAttributeValueController extends Controller
{
    public function index(Request $request)
    {
        $query = PrintingAttributeValue::query();
        if ($request->has('printing_attribute_id')) {
            $query->where('printing_attribute_id', $request->printing_attribute_id);
        }
        $values = $query->orderBy('sort_order')->get();
        return response()->json($values);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'printing_attribute_id' => 'required|exists:printing_attributes,id',
            'value' => 'required|string|max:255',
            'label' => 'required|string|max:255',
            'fixed_price' => 'integer|min:0',
            'unit_price' => 'integer|min:0',
            'multiplier_price' => 'integer|min:0',
            'weight' => 'numeric|min:0',
            'weight_type' => 'required|in:per_unit,per_sqm',
            'tiered_prices' => 'nullable|array',
            'added_production_days' => 'integer|min:0',
            'required_upload_category' => 'nullable|string|max:255',
            'sort_order' => 'integer'
        ]);

        $value = PrintingAttributeValue::create($validated);
        return response()->json($value, 201);
    }

    public function show(PrintingAttributeValue $printingAttributeValue)
    {
        return response()->json($printingAttributeValue);
    }

    public function update(Request $request, PrintingAttributeValue $printingAttributeValue)
    {
        $validated = $request->validate([
            'printing_attribute_id' => 'sometimes|exists:printing_attributes,id',
            'value' => 'sometimes|required|string|max:255',
            'label' => 'sometimes|required|string|max:255',
            'fixed_price' => 'integer|min:0',
            'unit_price' => 'integer|min:0',
            'multiplier_price' => 'integer|min:0',
            'weight' => 'numeric|min:0',
            'weight_type' => 'sometimes|required|in:per_unit,per_sqm',
            'tiered_prices' => 'nullable|array',
            'added_production_days' => 'integer|min:0',
            'required_upload_category' => 'nullable|string|max:255',
            'sort_order' => 'integer'
        ]);

        $printingAttributeValue->update($validated);
        return response()->json($printingAttributeValue);
    }

    public function destroy(PrintingAttributeValue $printingAttributeValue)
    {
        $printingAttributeValue->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
