<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\CustomerBrowsingHistory;

class TrackingController extends Controller
{
    public function track(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:enter,view,add_to_cart,checkout,purchase,exit',
            'path' => 'required|string',
            'title' => 'required|string',
        ]);

        $sessionId = $request->header('X-Session-ID');
        if (!$sessionId) {
            return response()->json(['message' => 'No session ID'], 400);
        }

        $storeId = $request->header('X-Store-ID') ?? config('tenant.current_store_id') ?? 1;
        $user = auth('sanctum')->user();
        
        $utmSource = $request->input('utm_source') ?? $request->input('ref');

        if ($user) {
            $customer = $user;
            // دمج أي أثر متبقي للجلسة الحالية في حساب المستخدم المسجل
            if ($customer->session_id !== $sessionId) {
                Customer::mergeGuestSession($customer, $sessionId);
            }

            // احتساب زيارة جديدة إذا مرت 30 دقيقة من عدم النشاط
            if ($customer->last_seen && $customer->last_seen->diffInMinutes(now()) >= 30) {
                $customer->increment('visits_count');
                $newSource = $this->detectSource($request->header('Referer'), $utmSource);
                if ($newSource !== 'direct' && (!$customer->source || $customer->source === 'direct')) {
                    $customer->update(['source' => $newSource]);
                }
            }
        } else {
            // البحث عن العميل أو إنشاء سجل زائر مجهول مرتبط بالمتجر والجلسة
            $customer = Customer::firstOrCreate(
                ['store_id' => $storeId, 'session_id' => $sessionId],
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'is_guest' => true,
                    'device' => $this->detectDevice($request->header('User-Agent')),
                    'source' => $this->detectSource($request->header('Referer'), $utmSource),
                    'visits_count' => 1,
                    'first_seen' => now(),
                    'last_seen' => now(),
                ]
            );

            // احتساب زيارة جديدة للزائر إذا مضت 30 دقيقة على آخر نشاط
            if (!$customer->wasRecentlyCreated) {
                if ($customer->last_seen && $customer->last_seen->diffInMinutes(now()) >= 30) {
                    $customer->increment('visits_count');
                    $newSource = $this->detectSource($request->header('Referer'), $utmSource);
                    if ($newSource !== 'direct' && (!$customer->source || $customer->source === 'direct')) {
                        $customer->update(['source' => $newSource]);
                    }
                } elseif (!$customer->source || $customer->source === 'direct') {
                    $newSource = $this->detectSource($request->header('Referer'), $utmSource);
                    if ($newSource !== 'direct') {
                        $customer->update(['source' => $newSource]);
                    }
                }
            }
        }

        // تحديث last_seen
        if (!$customer->last_seen || $customer->last_seen->diffInMinutes(now()) >= 2) {
            $customer->update(['last_seen' => now()]);
        }

        // تسجيل الحركة
        CustomerBrowsingHistory::create([
            'store_id' => $storeId,
            'customer_id' => $customer->id,
            'path' => $request->path,
            'title' => $request->title,
            'type' => $request->type,
            'visited_at' => now(),
        ]);

        return response()->json(['status' => 'recorded']);
    }

    private function detectDevice($userAgent)
    {
        if (!$userAgent) return 'Desktop';
        if (preg_match('/(iPhone|iPod|iPad|Android|BlackBerry|IEMobile)/', $userAgent)) return 'Mobile';
        return 'Desktop';
    }

    private function detectSource(?string $referer, ?string $utmSource = null): string
    {
        if ($utmSource) {
            $utm = strtolower($utmSource);
            if (
                str_contains($utm, 'chatgpt') ||
                str_contains($utm, 'openai') ||
                str_contains($utm, 'perplexity') ||
                str_contains($utm, 'claude') ||
                str_contains($utm, 'copilot') ||
                str_contains($utm, 'gemini') ||
                str_contains($utm, 'ai')
            ) {
                return 'ai';
            }
            if (str_contains($utm, 'tiktok')) return 'tiktok';
            if (str_contains($utm, 'facebook') || str_contains($utm, 'fb')) return 'facebook';
            if (str_contains($utm, 'instagram') || str_contains($utm, 'ig')) return 'instagram';
            if (str_contains($utm, 'google')) return 'google';
            if (str_contains($utm, 'snapchat')) return 'snapchat';
        }

        if (!$referer) return 'direct';

        $ref = strtolower($referer);

        // محركات ومنصات الذكاء الاصطناعي
        if (
            str_contains($ref, 'chatgpt.com') ||
            str_contains($ref, 'chat.openai.com') ||
            str_contains($ref, 'perplexity.ai') ||
            str_contains($ref, 'claude.ai') ||
            str_contains($ref, 'copilot.microsoft.com') ||
            str_contains($ref, 'gemini.google.com')
        ) {
            return 'ai';
        }

        // المنصات الاجتماعية ومحركات البحث
        if (str_contains($ref, 'tiktok.com')) return 'tiktok';
        if (str_contains($ref, 'google.')) return 'google';
        if (str_contains($ref, 'facebook.com') || str_contains($ref, 'fb.com')) return 'facebook';
        if (str_contains($ref, 'instagram.com')) return 'instagram';
        if (str_contains($ref, 'snapchat.com')) return 'snapchat';

        return 'referral';
    }
}
