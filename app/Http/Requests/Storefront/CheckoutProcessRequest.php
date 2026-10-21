<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Setting;

class CheckoutProcessRequest extends FormRequest
{
    public function authorize()
    {
        return true; 
    }

    public function rules()
    {
        $settings = Setting::getAllSettings();
        $codEnabled = filter_var($settings["cod_enabled"] ?? true, FILTER_VALIDATE_BOOLEAN);

        // Flexible payment methods allowing electronic gateways and manual transfers
        $validPaymentMethods = ["online", "kashier", "paymob", "instapay", "vodafone_cash"];
        if ($codEnabled) {
            $validPaymentMethods[] = "cod";
        }

        return [
            "customer_name" => "required|string|max:255",
            "customer_phone" => "required|string|max:20",
            "shipping_address" => "required|string",
            "zone_id" => "required|exists:shipping_zones,id",
            "shipping_method" => "nullable|string|in:standard,express",
            "payment_method" => ["required", "string", Rule::in($validPaymentMethods)],
            "payment_option" => "nullable|string|in:full,deposit",
            "coupon_code" => "nullable|string|max:50",
            "success_url" => "nullable|url",
            "failure_url" => "nullable|url",
        ];
    }
}
