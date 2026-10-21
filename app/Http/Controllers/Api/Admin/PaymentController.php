<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\PaymentGateway;

class PaymentController extends Controller
{
    public function getGateways()
    {
        $gateways = PaymentGateway::all()->map(function ($gateway) {
            if ($gateway->is_configured && is_array($gateway->credentials)) {
                $safeCredentials = $gateway->credentials;
                // Mock secret fields
                if (isset($safeCredentials['api_key'])) $safeCredentials['api_key'] = 'sk_hidden_***';
                if (isset($safeCredentials['webhook_secret'])) $safeCredentials['webhook_secret'] = 'ws_hidden_***';
                if (isset($safeCredentials['password'])) $safeCredentials['password'] = 'pw_hidden_***';
                if (isset($safeCredentials['account_pin'])) $safeCredentials['account_pin'] = 'pin_hidden_***';
                
                $gateway->credentials = $safeCredentials;
            } else {
                $gateway->credentials = null;
            }
            return $gateway;
        });

        return response()->json($gateways);
    }

    public function updateGateway(Request $request, $code)
    {
        $gateway = PaymentGateway::where('code', $code)->firstOrFail();
        
        $data = $request->validate([
            'is_active' => 'boolean',
            'credentials' => 'nullable|array'
        ]);

        if (isset($data['credentials'])) {
            $existingCredentials = $gateway->credentials ?? [];
            $newCredentials = $data['credentials'];
            
            foreach ($newCredentials as $key => $value) {
                // Ignore empty updates or mock strings for secret fields
                if (empty($value) || str_contains((string)$value, 'hidden_***')) {
                    continue;
                }
                $existingCredentials[$key] = $value;
            }

            $gateway->credentials = $existingCredentials;
            $gateway->is_configured = true;
        }

        if (isset($data['is_active'])) {
            $gateway->is_active = $data['is_active'];
        }

        $gateway->save();

        return response()->json(['message' => 'Payment gateway updated successfully']);
    }
}
