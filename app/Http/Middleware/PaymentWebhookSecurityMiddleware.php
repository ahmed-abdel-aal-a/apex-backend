<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class PaymentWebhookSecurityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = env('PAYMENT_WEBHOOK_ALLOWED_IPS', '');
        
        // As a strict security measure, if env is missing, we might deny all in production
        // But to prevent breaking, we allow all if explicitly empty, but log a warning.
        if (empty($allowedIps)) {
            Log::warning('PaymentWebhookSecurityMiddleware is active but PAYMENT_WEBHOOK_ALLOWED_IPS is not set in .env');
            return $next($request);
        }

        $allowedIpsArray = array_map('trim', explode(',', $allowedIps));

        if (!in_array($request->ip(), $allowedIpsArray)) {
            Log::critical('Blocked Webhook Spoofing Attempt from unauthorized IP: ' . $request->ip());
            return response()->json(['error' => 'Unauthorized Request Origin'], 403);
        }

        return $next($request);
    }
}
