<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShippingWebhookSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provider = $request->route('provider');
        
        $company = \App\Models\ShippingCompany::where('code', $provider)->first();
        if (!$company || !$company->is_active) {
            \Illuminate\Support\Facades\Log::warning("Shipping Webhook rejected: Provider {$provider} not found or inactive.");
            return response()->json(['message' => 'Shipping provider not found or inactive'], 404);
        }

        $credentials = $company->credentials ?? [];
        $secret = $credentials['webhook_secret'] ?? config("shipping.providers.{$provider}.webhook_secret");
        
        if (!$secret) {
            \Illuminate\Support\Facades\Log::critical("Shipping Webhook rejected: Provider {$provider} has no configured webhook secret.");
            return response()->json(['message' => 'Webhook provider has no configured secret'], 403);
        }

        $signature = $request->header('X-Webhook-Signature') 
            ?: $request->header('X-Signature') 
            ?: $request->header("X-{$provider}-Signature");
        
        if (!$signature || !$this->verifySignature($request->getContent(), $signature, $secret)) {
            \Illuminate\Support\Facades\Log::warning("Shipping Webhook signature mismatch for provider {$provider}.");
            return response()->json(['message' => 'Unauthorized webhook signature'], 401);
        }

        return $next($request);
    }

    /**
     * Verify HMAC SHA-256 Signature
     */
    protected function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}
