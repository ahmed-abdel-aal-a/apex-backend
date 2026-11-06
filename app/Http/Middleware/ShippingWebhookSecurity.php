<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ShippingWebhookSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provider = strtolower((string)$request->route('provider', ''));

        // 1. السماح بمرور طلبات GET دون أي اعتراض (Health Check لشركة بوسطة عند تسجيل الرابط)
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $company = \App\Models\ShippingCompany::where('code', $provider)->first();
        if (!$company || !$company->is_active) {
            Log::warning("Shipping Webhook rejected: Provider '{$provider}' not found or inactive.");
            return response()->json(['message' => 'Shipping provider not found or inactive'], 404);
        }

        // 2. قراءة المفتاح السري أولاً من موديل الشركة المشفر مع fallback لـ config
        $credentials = $company->credentials ?? [];
        $secret = $credentials['webhook_secret'] 
            ?? config("services.{$provider}.webhook_secret")
            ?? config("shipping.providers.{$provider}.webhook_secret");

        if (empty($secret)) {
            Log::critical("Shipping Webhook rejected: Provider '{$provider}' has no configured webhook secret.");
            return response()->json(['message' => 'Webhook provider has no configured secret'], 403);
        }

        // 3. التحقق الأمني لطلبات POST: دعم التوقيع المشفر أو الهيدر المباشر أو الـ Token
        $signature = $request->header('X-Webhook-Signature')
            ?: $request->header('X-Bosta-Signature')
            ?: $request->header('X-Signature')
            ?: $request->header("X-{$provider}-Signature")
            ?: $request->query('token');

        // إذا كان الهيدر يحتوي على Bearer Token مباشر
        $authHeader = $request->header('Authorization');
        if (!empty($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
            $bearer = substr($authHeader, 7);
            if (hash_equals($secret, $bearer)) {
                return $next($request);
            }
        }

        // التحقق من التوقيع بالـ Hash المباشر أو HMAC-SHA256
        $isValid = false;
        if (!empty($signature)) {
            if (hash_equals($secret, $signature)) {
                $isValid = true;
            } elseif ($this->verifyHmacSignature($request->getContent(), $signature, $secret)) {
                $isValid = true;
            }
        }

        if (!$isValid) {
            Log::warning("Shipping Webhook signature mismatch for provider '{$provider}'. Remote IP: {$request->ip()}");
            return response()->json(['message' => 'Unauthorized webhook signature'], 401);
        }

        return $next($request);
    }

    /**
     * Verify HMAC SHA-256 Signature
     */
    protected function verifyHmacSignature(string $payload, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }
}
