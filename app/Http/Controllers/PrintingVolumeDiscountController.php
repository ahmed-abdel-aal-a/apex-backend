<?php

namespace App\Http\Controllers;

use App\Models\PrintingVolumeDiscount;
use Illuminate\Http\Request;

class PrintingVolumeDiscountController extends Controller
{
    public function index(Request $request)
    {
        $query = PrintingVolumeDiscount::query();
        if ($request->has('printing_type_id')) {
            $query->where('printing_type_id', $request->printing_type_id);
        }
        $discounts = $query->orderBy('min_qty')->get();
        return response()->json($discounts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'printing_type_id' => 'required|exists:printing_types,id',
            'min_qty' => 'required|integer|min:1',
            'max_qty' => 'nullable|integer|gt:min_qty',
            'discount_basis_points' => 'required|integer|min:0|max:10000',
            'added_production_days' => 'integer|min:0'
        ]);

        $discount = PrintingVolumeDiscount::create($validated);
        return response()->json($discount, 201);
    }

    public function show(PrintingVolumeDiscount $printingVolumeDiscount)
    {
        return response()->json($printingVolumeDiscount);
    }

    public function update(Request $request, PrintingVolumeDiscount $printingVolumeDiscount)
    {
        $validated = $request->validate([
            'printing_type_id' => 'sometimes|exists:printing_types,id',
            'min_qty' => 'sometimes|required|integer|min:1',
            'max_qty' => 'nullable|integer|gt:min_qty',
            'discount_basis_points' => 'sometimes|required|integer|min:0|max:10000',
            'added_production_days' => 'integer|min:0'
        ]);

        $printingVolumeDiscount->update($validated);
        return response()->json($printingVolumeDiscount);
    }

    public function destroy(PrintingVolumeDiscount $printingVolumeDiscount)
    {
        $printingVolumeDiscount->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
