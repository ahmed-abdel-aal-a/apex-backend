<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products')->ignore($productId)],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'type' => ['required', 'in:simple,variable'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('products')->ignore($productId)],
            'track_inventory' => ['required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'starts_with:http://,https://'],
            'og_image' => ['nullable', 'string'],
            'is_indexable' => ['nullable', 'boolean'],
            'schema_markup' => ['nullable', 'json'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'addon_group_ids' => ['nullable', 'array'],
            'addon_group_ids.*' => ['integer', 'exists:addon_groups,id'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'exists:product_variants,id'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.discount_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sku' => [
                'nullable', 
                'string', 
                'max:255', 
                'distinct',
                function ($attribute, $value, $fail) {
                    preg_match('/variants\.(\d+)\.sku/', $attribute, $matches);
                    $index = $matches[1] ?? null;
                    $variantId = request()->input("variants.{$index}.id");

                    $query = \App\Models\ProductVariant::where('sku', $value);
                    if ($variantId) {
                        $query->where('id', '!=', $variantId);
                    }
                    if ($query->exists()) {
                        $fail('رمز الـ SKU للمتغير مستخدم مسبقاً في متغير آخر.');
                    }
                }
            ],
            'variants.*.track_inventory' => ['nullable', 'boolean'],
            'variants.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'variants.*.attributes' => ['array'],
        ];
    }
}
