<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreAddonGroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => 'required|array',
            'name.en' => 'required|string|min:2|max:100',
            'name.ar' => 'required|string|min:2|max:100',
            'description' => 'nullable|array',
            'description.en' => 'nullable|string|max:500',
            'description.ar' => 'nullable|string|max:500',
            'input_type' => 'required|in:checkbox,radio,select',
            'price_multiplier_type' => 'required|in:per_item,per_order_line',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
