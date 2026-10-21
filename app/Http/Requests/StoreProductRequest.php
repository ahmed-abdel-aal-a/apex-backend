<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // We will handle authorization via middleware/gates later
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Basic Product Fields
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'category_id' => 'required|exists:categories,id',
            'type' => 'required|in:simple,variable',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|unique:products,sku',
            'barcode' => 'nullable|string|unique:products,barcode',
            'track_inventory' => 'required|boolean',
            'is_active' => ['nullable', 'boolean'],
            'quantity' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'seo_title' => 'nullable|string|max:70',
            'seo_description' => ['nullable', 'string', 'max:160'],
            'meta_keywords' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|url|starts_with:http://,https://',
            'og_image' => 'nullable|string',
            'is_indexable' => 'nullable|boolean',
            'schema_markup' => 'nullable|json',
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'addon_group_ids' => ['nullable', 'array'],
            'addon_group_ids.*' => ['integer', 'exists:addon_groups,id'],

            // Variants Array Validation
            'variants' => 'nullable|array',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            'variants.*.discount_price' => 'nullable|numeric|min:0',
            'variants.*.sku' => 'nullable|string|distinct',
            'variants.*.track_inventory' => 'nullable|boolean',
            'variants.*.quantity' => 'nullable|integer|min:0',
            
            // Attributes array inside Variants
            'variants.*.attributes' => 'nullable|array',
            'variants.*.attributes.*.name' => 'required_with:variants.*.attributes|string',
            'variants.*.attributes.*.value' => 'required_with:variants.*.attributes|string',
        ];
    }
}
