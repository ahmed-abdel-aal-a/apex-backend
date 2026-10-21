<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Log;

class KashierService
{
    protected $merchantId;
    protected $apiKey;
    protected $webhookSecret;
    protected $baseUrl;
    protected $mode;

    public function __construct()
    {
        $gateway = PaymentGateway::where('code', 'kashier')->first();
        
        $credentials = $gateway ? $gateway->credentials : null;

        $this->merchantId = $credentials['merchant_id'] ?? env('KASHIER_MERCHANT_ID');
        $this->apiKey = $credentials['api_key'] ?? env('KASHIER_API_KEY');
        $this->webhookSecret = $credentials['webhook_secret'] ?? env('KASHIER_WEBHOOK_SECRET');
        $this->mode = $credentials['mode'] ?? env('KASHIER_MODE', 'test'); // test or live
        
        $this->baseUrl = $this->mode === 'live' 
            ? 'https://checkout.kashier.io' 
            : 'https://checkout.test.kashier.io';
    }

    /**
     * Generate the Kashier Checkout URL with HMAC hash
     */
    public function generateCheckoutUrl(Order $order, $successUrl = null, $failureUrl = null)
    {
        if (!$this->merchantId || !$this->apiKey) {
            Log::error('Kashier credentials are missing.');
            throw new \Exception('بوابة الدفع غير مهيأة بشكل صحيح.');
        }

        // Float precision mapping: Ensure EXACTLY 2 decimals as a string
        // If the order is configured as a deposit payment:
        // - If order is partially_paid, customer is paying the remaining_amount.
        // - If order is newly created, customer is paying the deposit_amount.
        // Otherwise, charge full total.
        if ($order->payment_type === 'deposit') {
            if ($order->payment_status === 'partially_paid') {
                $chargeAmount = (float)$order->remaining_amount;
            } elseif ((float)$order->deposit_amount > 0) {
                $chargeAmount = (float)$order->deposit_amount;
            } else {
                $chargeAmount = (float)$order->total;
            }
        } else {
            $chargeAmount = (float)$order->total;
        }

        $amount = number_format($chargeAmount, 2, '.', '');
        
        $currency = 'EGP';
        $orderId = (string)$order->id; // Kashier expects order ID

        // Construct the payload for hashing according to Kashier documentation
        // path: /?payment={merchantId}.{orderId}.{amount}.{currency}
        $path = "/?payment={$this->merchantId}.{$orderId}.{$amount}.{$currency}";

        // Generate HMAC SHA256 Hash
        $hash = hash_hmac('sha256', $path, $this->apiKey);

        $defaultSuccessUrl = url('/api/v1/payment/kashier/success');
        $defaultFailureUrl = url('/api/v1/payment/kashier/failure');

        $successUrl = $successUrl ?? $defaultSuccessUrl;
        $failureUrl = $failureUrl ?? $defaultFailureUrl;

        // Construct the full URL
        $query = http_build_query([
            'merchantId' => $this->merchantId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'hash' => $hash,
            'mode' => $this->mode,
            'metaData' => json_encode(['customer_name' => $order->customer_name, 'customer_phone' => $order->customer_phone]),
            'redirectMethod' => 'get',
            'successUrl' => $successUrl,
            'failureUrl' => $failureUrl,
            'display' => 'ar' // Arabic interface
        ]);

        return "{$this->baseUrl}/?{$query}";
    }

    /**
     * Verify Webhook Signature
     */
    public function verifyWebhookSignature($payload, $signature)
    {
        if (!$this->webhookSecret) {
            Log::error('Kashier Webhook Secret is not configured.');
            return false;
        }

        $calculatedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        
        return hash_equals($calculatedSignature, $signature);
    }
}
