<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentGateway;

class PaymentController extends Controller
{
    public function getGateways()
    {
        try {
            $gateways = PaymentGateway::all()->map(function ($gateway) {
                $safeCredentials = null;
                if ($gateway->is_configured) {
                    try {
                        $creds = $gateway->credentials;
                        if (!empty($creds)) {
                            $safe = is_array($creds) ? $creds : (method_exists($creds, 'toArray') ? $creds->toArray() : (array)$creds);
                            // Mock secret fields
                            if (isset($safe['api_key'])) $safe['api_key'] = 'sk_hidden_***';
                            if (isset($safe['webhook_secret'])) $safe['webhook_secret'] = 'ws_hidden_***';
                            if (isset($safe['password'])) $safe['password'] = 'pw_hidden_***';
                            if (isset($safe['account_pin'])) $safe['account_pin'] = 'pin_hidden_***';
                            $safeCredentials = $safe;
                        }
                    } catch (\Throwable $e) {
                        // Decryption fallback if APP_KEY changed
                        $safeCredentials = ['api_key' => 'sk_hidden_***'];
                    }
                }
                $gateway->credentials = $safeCredentials;
                return $gateway;
            });

            return response()->json($gateways);
        } catch (\Throwable $e) {
            Log::error('Error loading payment gateways: ' . $e->getMessage());
            return response()->json([], 200);
        }
    }

    public function updateGateway(Request $request, $code)
    {
        $gateway = PaymentGateway::where('code', $code)->firstOrFail();
        
        $data = $request->validate([
            'is_active' => 'boolean',
            'credentials' => 'nullable|array'
        ]);

        if (isset($data['credentials'])) {
            $existingCredentials = [];
            try {
                $existing = $gateway->credentials;
                if (!empty($existing)) {
                    $existingCredentials = is_array($existing) ? $existing : (method_exists($existing, 'toArray') ? $existing->toArray() : (array)$existing);
                }
            } catch (\Throwable $e) {
                $existingCredentials = [];
            }

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

