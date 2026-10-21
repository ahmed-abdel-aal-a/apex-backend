<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSocialAccount;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    /**
     * Redirect to the provider (Google/Facebook)
     */
    public function redirectToProvider($provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json(['error' => 'Provider not supported'], 400);
        }

        return response()->json([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl()
        ]);
    }

    /**
     * Handle the provider callback
     */
    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return redirect(env('STOREFRONT_OAUTH_CALLBACK_URL') . '?error=auth_failed');
        }

        // الحماية من حسابات السوشيال التي لا تحتوي على بريد إلكتروني
        if (!$socialUser->getEmail()) {
            return redirect(env('STOREFRONT_OAUTH_CALLBACK_URL') . '?error=no_email_provided');
        }

        $socialAccount = CustomerSocialAccount::where('provider_name', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $customer = $socialAccount->customer;
        } else {
            $customer = Customer::where('email', $socialUser->getEmail())->first();

            if ($customer) {
                if ($customer->is_guest) {
                    $customer->update([
                        'name' => $socialUser->getName(),
                        'is_guest' => false,
                        'password' => bcrypt(Str::random(16)),
                    ]);
                }
            } else {
                // التوليد الصريح للـ UUID كما هو مطلوب هندسياً
                $customer = Customer::create([
                    'id' => (string) Str::uuid(),
                    'name' => $socialUser->getName() ?? 'User',
                    'email' => $socialUser->getEmail(),
                    'password' => bcrypt(Str::random(16)),
                    'is_guest' => false,
                ]);
            }

            CustomerSocialAccount::create([
                'customer_id' => $customer->id,
                'provider_name' => $provider,
                'provider_id' => $socialUser->getId(),
            ]);
        }

        $token = $customer->createToken('storefront_token')->plainTextToken;

        $sessionId = request()->header('X-Session-ID') ?? request()->cookie('guest_session_id') ?? request()->query('session_id');
        if ($sessionId) {
            Customer::mergeGuestSession($customer, $sessionId);
        }

        $frontendUrl = env('STOREFRONT_OAUTH_CALLBACK_URL') . '?token=' . urlencode($token);
        return redirect($frontendUrl);
    }
}
