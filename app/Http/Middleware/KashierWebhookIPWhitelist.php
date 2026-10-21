<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class KashierWebhookIPWhitelist
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = env('KASHIER_ALLOWED_IPS', '');
        
        // If no IPs are configured, allow all (or depending on security policy, deny all)
        // For development/testing, it's common to allow all if empty.
        if (empty($allowedIps)) {
            return $next($request);
        }

        $allowedIpsArray = explode(',', $allowedIps);
        $allowedIpsArray = array_map('trim', $allowedIpsArray);

        if (!in_array($request->ip(), $allowedIpsArray)) {
            Log::warning('Unauthorized Webhook IP Attempt from: ' . $request->ip());
            return response()->json(['error' => 'Unauthorized IP address'], 403);
        }

        return $next($request);
    }
}
