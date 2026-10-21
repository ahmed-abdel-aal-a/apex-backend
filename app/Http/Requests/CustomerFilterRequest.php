<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerFilterRequest extends FormRequest
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
        return [
            'search' => 'nullable|string|max:255',
            'source' => 'nullable|in:direct,facebook,instagram,tiktok,google,snapchat,referral,ai',
            'device' => 'nullable|in:mobile,desktop,tablet,Mobile,Desktop,Tablet',
            'sort' => 'nullable|in:top_spenders,latest,oldest',
            'min_spent' => 'nullable|numeric|min:0',
            'max_spent' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,banned',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ];
    }
}
