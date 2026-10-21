<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFaqQuestionRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sectionId' => 'required|exists:faq_sections,id',
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:2000',
            'isActive' => 'boolean',
            'order' => 'integer|min:0',
        ];
    }
}
