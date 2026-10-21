<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductAddonRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'addon_group_id' => 'required|exists:addon_groups,id',
            'name' => 'required|array',
            'name.en' => 'required|string|min:2|max:100',
            'name.ar' => 'required|string|min:2|max:100',
            'description' => 'nullable|array',
            'description.en' => 'nullable|string|max:500',
            'description.ar' => 'nullable|string|max:500',
            'type' => 'required|in:simple,printing_linked',
            'price' => 'required|numeric|min:0',
            'price_type' => 'required|in:fixed,percentage',
            'printing_type_id' => 'required_if:type,printing_linked|exists:printing_types,id',
            'requires_upload' => 'boolean',
            'track_inventory' => 'boolean',
            'quantity' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
