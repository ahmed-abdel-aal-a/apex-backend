<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChatSettingsRequest extends FormRequest
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
            'isMessageEnabled' => 'nullable|boolean',
            'messageText' => 'nullable|string|max:1000',
            'displayLocation' => 'nullable|string|in:chat_only,chat_and_faq,bottom-right',
            'themeColor' => 'nullable|string|regex:/^#[a-fA-F0-9]{6}$/',
            'whatsappNumber' => 'nullable|string|max:30',
            'whatsapp_number' => 'nullable|string|max:30',
            'whatsappEnabled' => 'nullable|boolean',
            'whatsapp_enabled' => 'nullable|boolean',
            'whatsappDefaultMessage' => 'nullable|string|max:1000',
            'whatsapp_default_message' => 'nullable|string|max:1000',
        ];
    }
}
