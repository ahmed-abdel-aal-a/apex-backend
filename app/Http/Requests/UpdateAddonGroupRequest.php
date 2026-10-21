<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAddonGroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|array',
            'name.en' => 'required_with:name|string|min:2|max:100',
            'name.ar' => 'required_with:name|string|min:2|max:100',
            'description' => 'nullable|array',
            'description.en' => 'nullable|string|max:500',
            'description.ar' => 'nullable|string|max:500',
            'input_type' => 'sometimes|in:checkbox,radio,select',
            'price_multiplier_type' => 'sometimes|in:per_item,per_order_line',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
