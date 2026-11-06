<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\ShippingCompany;
use App\Models\ShippingZone;
use App\Services\Shipping\ShippingFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShippingController extends Controller
{
    public function getCompanies()
    {
        // Don't send actual credentials to frontend, just send if it's configured
        $companies = ShippingCompany::all()->map(function ($company) {
            $company->credentials = $company->is_configured ? ['api_key' => 'sk_hidden_***'] : null;
            return $company;
        });
        
        return response()->json($companies);
    }

    public function updateCompany(Request $request, $code)
    {
        $company = ShippingCompany::where('code', $code)->firstOrFail();
        
        $data = $request->validate([
            'is_active' => 'boolean',
            'credentials' => 'nullable|array'
        ]);

        if (isset($data['credentials'])) {
            $company->credentials = $data['credentials'];
            $company->is_configured = true;
        }

        if (isset($data['is_active'])) {
            $company->is_active = $data['is_active'];
        }

        $company->save();

        return response()->json(['message' => 'Shipping company updated successfully']);
    }

    public function getZones()
    {
        return response()->json(ShippingZone::all());
    }

    public function updateZones(Request $request)
    {
        $data = $request->validate([
            'zones' => 'required|array',
            'zones.*.id' => 'required|exists:shipping_zones,id',
            'zones.*.rate' => 'required|numeric',
            'zones.*.delivery_days' => 'nullable|string|max:50'
        ]);

        foreach ($data['zones'] as $zoneData) {
            $updatePayload = ['rate' => $zoneData['rate']];
            if (array_key_exists('delivery_days', $zoneData)) {
                $updatePayload['delivery_days'] = $zoneData['delivery_days'];
            }
            ShippingZone::where('id', $zoneData['id'])->update($updatePayload);
        }

        return response()->json(['message' => 'Shipping zones updated successfully']);
    }

    /**
     * سحب وطباعة البوليصة الرسمية AWB من بوسطة (A6 استيكر حراري أو A4)
     * مع التوثيق الكامل لترويسة RFC 6266 والتحقق من سلامة ترويسة %PDF-
     */
    public function getAwb(Request $request, $id, ShippingFactory $shippingFactory)
    {
        $cleanId = preg_replace('/[^0-9]/', '', (string)$id);
        $orderId = !empty($cleanId) ? (int)$cleanId : $id;

        $order = Order::findOrFail($orderId);

        if (empty($order->tracking_number) && empty($order->shipping_provider_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'هذا الطلب ليس له بوليصة شحن مسجلة بعد مع بوسطة.'
            ], 404);
        }

        $format = strtoupper((string)$request->query('format', 'A6')) === 'A4' ? 'A4' : 'A6';

        try {
            $provider = $shippingFactory->getProvider($order);
            if (!method_exists($provider, 'getAwbPdf')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'مزود الشحن الحالي لا يدعم طباعة البوليصة المباشرة.'
                ], 400);
            }

            $pdfBinary = $provider->getAwbPdf($order, $format, 'ar');

            $fileName = "Bosta-AWB-{$order->tracking_number}-{$format}.pdf";

            return response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$fileName}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (Throwable $e) {
            Log::error("[ShippingController@getAwb] Error for Order {$order->id}: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * إعادة محاولة إصدار البوليصة لحظياً (Synchronously) من لوحة التحكم
     * بعد قيام المشرف بتصحيح بيانات هاتف أو عنوان العميل
     */
    public function retryWaybill(Request $request, $id, ShippingFactory $shippingFactory)
    {
        $cleanId = preg_replace('/[^0-9]/', '', (string)$id);
        $orderId = !empty($cleanId) ? (int)$cleanId : $id;

        try {
            return DB::transaction(function () use ($orderId, $shippingFactory) {
                $order = Order::lockForUpdate()->findOrFail($orderId);

                // التأكد من الحالات المسموح بإصدار شحنة لها
                if (in_array($order->status, ['pending_price_confirmation', 'cancelled', 'refunded', 'completed', 'delivered'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "لا يمكن إصدار بوليصة شحن لطلب في حالة '{$order->status}'."
                    ], 422);
                }

                $provider = $shippingFactory->getProvider($order);
                $result = $provider->createWaybill($order);

                // تحديث بيانات الطلب بنجاح
                $order->tracking_number = $result['tracking_number'];
                $order->shipping_provider_id = $result['shipping_provider_id'] ?? null;
                $order->shipping_company = 'bosta';
                $order->shipping_status = 'ready_for_pickup';
                $order->latest_shipping_update_at = now();
                $order->shipping_error_log = null; // مسح رسالة الخطأ السابقة

                if (in_array($order->status, ['ready_for_shipping', 'processing', 'confirmed'])) {
                    $order->status = 'ready_for_pickup';
                }

                $order->save();

                Log::info("[ShippingController@retryWaybill] Synchronously generated Bosta waybill {$order->tracking_number} for Order {$order->id}");

                return response()->json([
                    'status' => 'success',
                    'message' => "تم إصدار بوليصة الشحن بنجاح برقم: {$order->tracking_number}",
                    'order' => $order->fresh(['items']),
                ]);
            });
        } catch (Throwable $e) {
            Log::error("[ShippingController@retryWaybill] Failed for Order {$orderId}: " . $e->getMessage());

            // توثيق الخطأ على الطلب حتى لو فشلت العملية
            $order = Order::find($orderId);
            if ($order) {
                $order->shipping_error_log = $e->getMessage();
                $order->save();
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
