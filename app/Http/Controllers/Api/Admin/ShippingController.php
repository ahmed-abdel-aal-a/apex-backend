<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ShippingCompany;
use App\Models\ShippingZone;

class ShippingController extends Controller
{
    public function getCompanies()
    {
        // Don't send actual credentials to frontend, just send if it's configured
        $companies = ShippingCompany::all()->map(function ($company) {
            $company->credentials = $company->is_configured ? ['api_key' => 'sk_hidden_***'] : null;
            return $company;
        });
        
        return response()->json($companies);
    }

    public function updateCompany(Request $request, $code)
    {
        $company = ShippingCompany::where('code', $code)->firstOrFail();
        
        $data = $request->validate([
            'is_active' => 'boolean',
            'credentials' => 'nullable|array'
        ]);

        if (isset($data['credentials'])) {
            $company->credentials = $data['credentials'];
            $company->is_configured = true;
        }

        if (isset($data['is_active'])) {
            $company->is_active = $data['is_active'];
        }

        $company->save();

        return response()->json(['message' => 'Shipping company updated successfully']);
    }

    public function getZones()
    {
        return response()->json(ShippingZone::all());
    }

    public function updateZones(Request $request)
    {
        $data = $request->validate([
            'zones' => 'required|array',
            'zones.*.id' => 'required|exists:shipping_zones,id',
            'zones.*.rate' => 'required|numeric',
            'zones.*.delivery_days' => 'nullable|string|max:50'
        ]);

        foreach ($data['zones'] as $zoneData) {
            $updatePayload = ['rate' => $zoneData['rate']];
            if (array_key_exists('delivery_days', $zoneData)) {
                $updatePayload['delivery_days'] = $zoneData['delivery_days'];
            }
            ShippingZone::where('id', $zoneData['id'])->update($updatePayload);
        }

        return response()->json(['message' => 'Shipping zones updated successfully']);
    }
}
