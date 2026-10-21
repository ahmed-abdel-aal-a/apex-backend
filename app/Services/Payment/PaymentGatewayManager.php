<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class PaymentGatewayManager
{
    /**
     * Get the active payment gateway code safely.
     * Throws exception if multiple are active to prevent collision.
     * Throws exception if none are active.
     */
    public static function getActiveGatewayCode(): string
    {
        $activeGateways = PaymentGateway::where('is_active', true)
                                        ->where('is_configured', true)
                                        ->get();

        if ($activeGateways->count() > 1) {
            Log::critical('Security Alert: Multiple active payment gateways detected.');
            throw ValidationException::withMessages([
                'payment' => 'يوجد خطأ فادح في إعدادات الدفع. يرجى إبلاغ الإدارة فوراً.'
            ]);
        }

        $activeGateway = $activeGateways->first();

        if (!$activeGateway) {
            throw ValidationException::withMessages([
                'payment' => 'الدفع الإلكتروني غير متاح حالياً.'
            ]);
        }

        return $activeGateway->code;
    }

    /**
     * Resolve the appropriate payment service based on gateway code.
     */
    public static function resolveService(string $code)
    {
        switch ($code) {
            case 'kashier':
                return app(KashierService::class);
            case 'paymob':
                throw new \Exception("PaymobService not yet fully implemented.");
            default:
                throw new \Exception("Unknown payment gateway: {$code}");
        }
    }
}
