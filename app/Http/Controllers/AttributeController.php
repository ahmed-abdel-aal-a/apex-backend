<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttributeController extends Controller
{
    public function index(): JsonResponse
    {
        $attributes = Attribute::all();
        return response()->json($attributes, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'values' => ['required', 'array'],
            'values.*' => ['string'],
        ]);

        $attribute = Attribute::create($validated);
        return response()->json($attribute, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'values' => ['required', 'array'],
            'values.*' => ['string'],
        ]);

        $attribute = Attribute::findOrFail($id);
        $attribute->update(['values' => $validated['values']]);
        
        return response()->json($attribute, 200);
    }

    public function destroy($id): JsonResponse
    {
        $attribute = Attribute::findOrFail($id);
        $attributeName = $attribute->name;

        // التحقق من عدم ارتباط الخاصية بأي متغيرات منتجات نشطة
        $isUsedInVariants = \App\Models\ProductVariant::whereNotNull('attributes')
            ->get()
            ->contains(function ($variant) use ($attributeName) {
                return is_array($variant->attributes) && array_key_exists($attributeName, $variant->attributes);
            });

        if ($isUsedInVariants) {
            return response()->json([
                'message' => 'لا يمكن حذف هذه الخاصية لأنها مرتبطة بمتغيرات منتجات موجودة حالياً.'
            ], 422);
        }

        $attribute->delete();

        return response()->json([
            'message' => 'تم حذف الخاصية بنجاح'
        ], 200);
    }
}
