<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index()
    {
        return response()->json(Setting::getAllSettings());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cod_enabled' => 'boolean',
            'cod_fee' => 'numeric',
            'deposit_enabled' => 'boolean',
            'deposit_percentage' => 'numeric|min:1|max:100',
            'min_deposit_amount' => 'numeric|min:0',
            'printing_payment_mode' => 'string|in:full_only,deposit_only,customer_choice',
            'deposit_calculation_basis' => 'nullable|string|in:printing_only,all_items',
            'allow_full_cod_for_printing' => 'boolean',
            'free_shipping_enabled' => 'boolean',
            'free_shipping_threshold' => 'numeric',
            'warehouse_governorate' => 'string',
            'warehouse_city' => 'string',
            'warehouse_address' => 'string',
            'express_shipping_enabled' => 'boolean',
            'express_shipping_fee' => 'numeric',
            'express_shipping_delivery_time' => 'nullable|string',
            'standard_shipping_company' => 'nullable|string',
            'standard_shipping_delivery_time' => 'nullable|string',
            'express_shipping_company' => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
            'store_name' => 'nullable|string',
            'store_email' => 'nullable|string',
            'store_phone' => 'nullable|string',
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        // Cache is automatically cleared by the Setting model's `saved` event observer.

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => Setting::getAllSettings()
        ]);
    }
}
