<?php

namespace App\Http\Controllers;

use App\Models\PrintingAttribute;
use Illuminate\Http\Request;

class PrintingAttributeController extends Controller
{
    public function index(Request $request)
    {
        $query = PrintingAttribute::query();
        if ($request->has('printing_type_id')) {
            $query->where('printing_type_id', $request->printing_type_id);
        }
        $attributes = $query->orderBy('sort_order')->get();
        return response()->json($attributes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'printing_type_id' => 'required|exists:printing_types,id',
            'name' => 'required|string|max:255',
            'label' => 'required|string|max:255',
            'type' => 'required|in:select,radio,checkbox,number,multiplier_number',
            'step' => 'required|in:specifications,finishing',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'min_value' => 'nullable|integer',
            'max_value' => 'nullable|integer',
            'conditional_rules' => 'nullable|array',
            'linked_upload_category' => 'nullable|string|max:255',
            'is_dimension_width' => 'boolean',
            'is_dimension_length' => 'boolean',
        ]);

        if ($request->is_dimension_width) {
            $exists = PrintingAttribute::where('printing_type_id', $request->printing_type_id)
                ->where('is_dimension_width', true)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'لا يمكن تحديد أكثر من عرض واحد لنفس نوع الطباعة'], 422);
            }
        }

        if ($request->is_dimension_length) {
            $exists = PrintingAttribute::where('printing_type_id', $request->printing_type_id)
                ->where('is_dimension_length', true)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'لا يمكن تحديد أكثر من طول واحد لنفس نوع الطباعة'], 422);
            }
        }

        $attribute = PrintingAttribute::create($validated);
        return response()->json($attribute, 201);
    }

    public function show(PrintingAttribute $printingAttribute)
    {
        return response()->json($printingAttribute->load('values'));
    }

    public function update(Request $request, PrintingAttribute $printingAttribute)
    {
        $validated = $request->validate([
            'printing_type_id' => 'sometimes|exists:printing_types,id',
            'name' => 'sometimes|required|string|max:255',
            'label' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:select,radio,checkbox,number,multiplier_number',
            'step' => 'sometimes|required|in:specifications,finishing',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'min_value' => 'nullable|integer',
            'max_value' => 'nullable|integer',
            'conditional_rules' => 'nullable|array',
            'linked_upload_category' => 'nullable|string|max:255',
            'is_dimension_width' => 'boolean',
            'is_dimension_length' => 'boolean',
        ]);

        $typeId = $request->printing_type_id ?? $printingAttribute->printing_type_id;

        if ($request->is_dimension_width) {
            $exists = PrintingAttribute::where('printing_type_id', $typeId)
                ->where('is_dimension_width', true)
                ->where('id', '!=', $printingAttribute->id)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'لا يمكن تحديد أكثر من عرض واحد لنفس نوع الطباعة'], 422);
            }
        }

        if ($request->is_dimension_length) {
            $exists = PrintingAttribute::where('printing_type_id', $typeId)
                ->where('is_dimension_length', true)
                ->where('id', '!=', $printingAttribute->id)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'لا يمكن تحديد أكثر من طول واحد لنفس نوع الطباعة'], 422);
            }
        }

        $printingAttribute->update($validated);
        return response()->json($printingAttribute);
    }

    public function destroy(PrintingAttribute $printingAttribute)
    {
        $printingAttribute->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
