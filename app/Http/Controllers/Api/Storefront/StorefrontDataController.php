<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductResource;
use Illuminate\Support\Facades\Cache;

class StorefrontDataController extends Controller
{
    public function getShippingZones()
    {
        return response()->json(ShippingZone::all());
    }

    public function getCategories()
    {
        // For storefront, we only fetch active categories
        $categories = Category::where('is_active', true)->get();
        return CategoryResource::collection($categories);
    }

    public function getProducts(Request $request)
    {
        $version = Cache::get('products_cache_version', 1);
        $cacheKey = 'storefront_products_v' . $version . '_' . md5(json_encode($request->all()) . '_page_' . $request->get('page', 1));

        $products = Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = Product::with([
                'variants', 
                'stockReservations' => fn($q) => $q->where('expires_at', '>', now()), 
                'variants.stockReservations' => fn($q) => $q->where('expires_at', '>', now())
            ])->where('is_active', true);

            $catParam = $request->get('category_id') ?? $request->get('category_slug');
            if ($catParam && $catParam !== 'الكل' && $catParam !== 'all') {
                $catParam = urldecode((string) $catParam);
                if (is_numeric($catParam)) {
                    $query->where('category_id', (int)$catParam);
                } else {
                    $cat = Category::where('slug', $catParam)->first();
                    if ($cat) {
                        $query->where('category_id', $cat->id);
                    } else {
                        $query->where('category_id', -1);
                    }
                }
            }

            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'like', "%{$request->search}%");
            }

            if ($request->has('min_price')) {
                $query->whereRaw('COALESCE(discount_price, price) >= ?', [$request->min_price]);
            }

            if ($request->has('max_price')) {
                $query->whereRaw('COALESCE(discount_price, price) <= ?', [$request->max_price]);
            }

            return $query->paginate(20);
        });

        return ProductResource::collection($products);
    }

    public function getProduct($id)
    {
        $id = urldecode((string) $id);
        $cacheKey = 'storefront_product_' . $id;

        $product = Cache::remember($cacheKey, 300, function () use ($id) {
            $query = Product::with([
                'category',
                'variants',
                'stockReservations' => fn($q) => $q->where('expires_at', '>', now()),
                'variants.stockReservations' => fn($q) => $q->where('expires_at', '>', now()),
                'addonGroups.addons' => fn($q) => $q->where('is_active', true),
                'category.addonGroups.addons' => fn($q) => $q->where('is_active', true),
            ])
            ->where('is_active', true);

            if (is_numeric($id)) {
                $product = (clone $query)->where('id', (int)$id)->first();
                if (!$product) {
                    $product = (clone $query)->where('slug', $id)->first();
                }
            } else {
                $product = $query->where('slug', $id)->first();
            }

            if (!$product) {
                abort(404, 'Product not found');
            }

            return $product;
        });

        return new ProductResource($product);
    }

    public function getCheckoutSettings()
    {
        // 1. Get COD settings from global store settings
        $settings = \App\Models\Setting::getAllSettings();
        
        // Ensure boolean cast, default to true if not set
        $codEnabled = isset($settings['cod_enabled']) ? filter_var($settings['cod_enabled'], FILTER_VALIDATE_BOOLEAN) : true;
        $codFee = isset($settings['cod_fee']) ? (float)$settings['cod_fee'] : 0;

        // 2. Check if ANY online gateway is active and configured
        $onlinePaymentEnabled = \App\Models\PaymentGateway::where('is_active', true)
                                ->where('is_configured', true)
                                ->exists();

        $standardShippingDeliveryTime = $settings['standard_shipping_delivery_time'] ?? 'خلال 2 - 4 أيام عمل';
        $expressShippingEnabled = isset($settings['express_shipping_enabled']) ? filter_var($settings['express_shipping_enabled'], FILTER_VALIDATE_BOOLEAN) : false;
        $expressShippingFee = isset($settings['express_shipping_fee']) ? (float)$settings['express_shipping_fee'] : 0;
        $expressShippingDeliveryTime = $settings['express_shipping_delivery_time'] ?? 'خلال 24 - 48 ساعة';

        $freeShippingEnabled = isset($settings['free_shipping_enabled']) ? filter_var($settings['free_shipping_enabled'], FILTER_VALIDATE_BOOLEAN) : false;
        $freeShippingThreshold = isset($settings['free_shipping_threshold']) ? (float)$settings['free_shipping_threshold'] : 0;

        // 3. Deposit & Printing Payment Policies
        $depositEnabled = isset($settings['deposit_enabled']) ? filter_var($settings['deposit_enabled'], FILTER_VALIDATE_BOOLEAN) : true;
        $depositPercentage = isset($settings['deposit_percentage']) ? (float)$settings['deposit_percentage'] : 30.0;
        $minDepositAmount = isset($settings['min_deposit_amount']) ? (float)$settings['min_deposit_amount'] : 50.0;
        $printingPaymentMode = $settings['printing_payment_mode'] ?? 'customer_choice';
        $depositCalculationBasis = $settings['deposit_calculation_basis'] ?? 'printing_only';
        $allowFullCodForPrinting = isset($settings['allow_full_cod_for_printing']) ? filter_var($settings['allow_full_cod_for_printing'], FILTER_VALIDATE_BOOLEAN) : false;

        // DTO strictly formatted to prevent data leakage (No credentials, no gateway names)
        return response()->json([
            'data' => [
                'cod_enabled' => $codEnabled,
                'cod_fee' => $codFee,
                'online_payment_enabled' => $onlinePaymentEnabled,
                'deposit_enabled' => $depositEnabled,
                'deposit_percentage' => $depositPercentage,
                'min_deposit_amount' => $minDepositAmount,
                'printing_payment_mode' => $printingPaymentMode,
                'deposit_calculation_basis' => $depositCalculationBasis,
                'allow_full_cod_for_printing' => $allowFullCodForPrinting,
                'standard_shipping_delivery_time' => $standardShippingDeliveryTime,
                'express_shipping_enabled' => $expressShippingEnabled,
                'express_shipping_fee' => $expressShippingFee,
                'express_shipping_delivery_time' => $expressShippingDeliveryTime,
                'free_shipping_enabled' => $freeShippingEnabled,
                'free_shipping_threshold' => $freeShippingThreshold,
                'whatsapp_number' => $settings['whatsapp_number'] ?? '01000000000',
            ]
        ]);
    }
}
