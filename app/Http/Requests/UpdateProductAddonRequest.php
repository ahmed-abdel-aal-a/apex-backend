<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductAddonRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'addon_group_id' => 'sometimes|exists:addon_groups,id',
            'name' => 'sometimes|required|array',
            'name.en' => 'required_with:name|string|min:2|max:100',
            'name.ar' => 'required_with:name|string|min:2|max:100',
            'description' => 'nullable|array',
            'description.en' => 'nullable|string|max:500',
            'description.ar' => 'nullable|string|max:500',
            'type' => 'sometimes|in:simple,printing_linked',
            'price' => 'sometimes|numeric|min:0',
            'price_type' => 'sometimes|in:fixed,percentage',
            'printing_type_id' => 'required_if:type,printing_linked|nullable|exists:printing_types,id',
            'requires_upload' => 'boolean',
            'track_inventory' => 'boolean',
            'quantity' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
