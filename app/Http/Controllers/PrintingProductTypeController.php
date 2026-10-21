<?php

namespace App\Http\Controllers;

use App\Models\PrintingProductType;
use Illuminate\Http\Request;

class PrintingProductTypeController extends Controller
{
    public function index()
    {
        $types = PrintingProductType::orderBy('sort_order')->get();
        return response()->json($types);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'allow_custom_name' => 'boolean',
            'sort_order' => 'integer'
        ]);

        $type = PrintingProductType::create($validated);
        return response()->json($type, 201);
    }

    public function show(PrintingProductType $printingProductType)
    {
        return response()->json($printingProductType->load('printingTypes'));
    }

    public function update(Request $request, PrintingProductType $printingProductType)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'allow_custom_name' => 'boolean',
            'sort_order' => 'integer'
        ]);

        $printingProductType->update($validated);
        return response()->json($printingProductType);
    }

    public function destroy(PrintingProductType $printingProductType)
    {
        $printingProductType->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
