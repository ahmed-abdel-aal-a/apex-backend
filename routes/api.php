<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==========================================
// Health Check Endpoint
// ==========================================
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Apex Store API is running smoothly.',
        'timestamp' => now()
    ], 200);
});

// ==========================================
// Admin Auth Routes
// ==========================================
use App\Http\Controllers\Admin\AuthController as AdminAuthController;

Route::prefix('v1/admin/auth')->group(function () {
    // Rate Limiting: 5 requests per minute for login
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth:sanctum')->prefix('v1/admin/auth')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me', [AdminAuthController::class, 'me']);
});


// ==========================================
// Admin Protected Routes
// ==========================================
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AbandonedCartController;
use App\Http\Controllers\Admin\ChatCenter\FaqController;
use App\Http\Controllers\Admin\ChatCenter\SettingsController as ChatSettingsController;
use App\Http\Controllers\Admin\ChatCenter\ConversationController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\Admin\ShippingController as AdminShippingController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PrintingProductTypeController;
use App\Http\Controllers\PrintingTypeController;
use App\Http\Controllers\PrintingAttributeController;
use App\Http\Controllers\PrintingAttributeValueController;
use App\Http\Controllers\PrintingVolumeDiscountController;
use App\Http\Controllers\Admin\AddonGroupController;
use App\Http\Controllers\Admin\ProductAddonController;
Route::middleware('auth:sanctum')->prefix('v1/admin')->group(function () {

    // Push Subscriptions
    Route::post('/push-subscriptions', [\App\Http\Controllers\Api\NotificationController::class, 'subscribe']);

    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/reports', [DashboardController::class, 'reports']);

    // Staff Management
    Route::apiResource('staff', StaffController::class);

    // Customers
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/stats/sources', [CustomerController::class, 'sourceStats']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::get('/customers/{id}/history', [CustomerController::class, 'history']);

    // Categories & Attributes
    Route::post('/categories/bulk-delete', [CategoryController::class, 'bulkDestroy']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('attributes', AttributeController::class)->only(['index', 'store', 'update', 'destroy']);
    
    // Products & Printing
    Route::middleware('permission:manage products')->group(function () {
        Route::post('/products/bulk-delete', [ProductController::class, 'bulkDestroy']);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('printing-product-types', PrintingProductTypeController::class);
        Route::apiResource('printing-types', PrintingTypeController::class);
        Route::apiResource('printing-attributes', PrintingAttributeController::class);
        Route::apiResource('printing-attribute-values', PrintingAttributeValueController::class);
        Route::apiResource('printing-volume-discounts', PrintingVolumeDiscountController::class);
    });
    Route::post('/upload', [UploadController::class, 'upload']);
    Route::post('/upload-general', [UploadController::class, 'uploadGeneral']);
    
    // Addons
    Route::apiResource('addon-groups', AddonGroupController::class);
    Route::apiResource('addon-groups.addons', ProductAddonController::class)->scoped();
    Route::get('/orders/items/addons/{addonId}/files', [\App\Http\Controllers\Api\Admin\OrderItemAddonFileController::class, 'getFiles']);
    Route::get('/orders/items/addons/{addonId}/files/{fileIndex}/download', [\App\Http\Controllers\Api\Admin\OrderItemAddonFileController::class, 'downloadFile']);
    
    // Blog
    Route::apiResource('blog-posts', App\Http\Controllers\Admin\BlogPostController::class);
    
    // Offers
    Route::apiResource('offers', App\Http\Controllers\Api\Admin\OfferController::class);

    // Coupons
    Route::patch('/coupons/{coupon}/toggle-active', [\App\Http\Controllers\Api\Admin\CouponController::class, 'toggleActive']);
    Route::apiResource('coupons', \App\Http\Controllers\Api\Admin\CouponController::class);

    // Orders
    Route::get('/orders/export', [OrderController::class, 'export']);
    Route::get('/orders/summary', [OrderController::class, 'summary']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::patch('/orders/items/{item_id}/file-status', [OrderController::class, 'updateItemFileStatus']);
    Route::get('/orders/items/{itemId}/files/{category}/download', [OrderController::class, 'downloadPrintFile']);
    Route::patch('/orders/{orderId}/items/{itemId}/confirm-price', [OrderController::class, 'confirmItemPrice']);
    Route::post('/orders/{orderId}/issue-price-confirmation', [OrderController::class, 'issuePriceConfirmation']);
    Route::post('/orders/{orderId}/confirm-manual-payment', [OrderController::class, 'confirmManualPayment']);

    // Abandoned Carts
    Route::get('/abandoned-carts', [AbandonedCartController::class, 'index']);
    Route::get('/abandoned-carts/settings', [AbandonedCartController::class, 'getSettings']);
    Route::put('/abandoned-carts/settings', [AbandonedCartController::class, 'updateSettings']);
    Route::get('/abandoned-carts/{id}', [AbandonedCartController::class, 'show']);
    Route::post('/abandoned-carts/{id}/generate-discount', [AbandonedCartController::class, 'generateDiscount']);
    Route::post('/abandoned-carts/{id}/push-notification', [AbandonedCartController::class, 'sendPushNotification']);
    Route::post('/abandoned-carts/{id}/contact-history', [AbandonedCartController::class, 'logContactEvent']);

    // Chat Center
    Route::prefix('chat-center')->group(function () {
        Route::get('/faqs', [FaqController::class, 'index']);
        Route::post('/faqs/sections', [FaqController::class, 'storeSection']);
        Route::put('/faqs/sections/{id}', [FaqController::class, 'updateSection']);
        Route::delete('/faqs/sections/{id}', [FaqController::class, 'destroySection']);
        Route::post('/faqs/questions', [FaqController::class, 'storeQuestion']);
        Route::put('/faqs/questions/{id}', [FaqController::class, 'updateQuestion']);
        Route::delete('/faqs/questions/{id}', [FaqController::class, 'destroyQuestion']);
        Route::put('/faqs/reorder', [FaqController::class, 'reorder']);

        Route::get('/settings', [ChatSettingsController::class, 'index']);
        Route::put('/settings', [ChatSettingsController::class, 'update']);

        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{id}/messages', [ConversationController::class, 'storeMessage'])->middleware('throttle:chat-messages');
        Route::put('/conversations/{id}/read', [ConversationController::class, 'markAsRead']);
        Route::put('/conversations/{id}/end', [ConversationController::class, 'endConversation']);
        Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);
    });

    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/general', [AdminSettingsController::class, 'index']);
        Route::put('/general', [AdminSettingsController::class, 'update']);

        Route::get('/shipping/companies', [AdminShippingController::class, 'getCompanies']);
        Route::put('/shipping/companies/{code}', [AdminShippingController::class, 'updateCompany']);
        Route::get('/shipping/zones', [AdminShippingController::class, 'getZones']);
        Route::put('/shipping/zones', [AdminShippingController::class, 'updateZones']);

        Route::get('/payment/gateways', [AdminPaymentController::class, 'getGateways']);
        Route::put('/payment/gateways/{code}', [AdminPaymentController::class, 'updateGateway']);
    });

});

// ==========================================
// Storefront Auth Routes
// ==========================================
use App\Http\Controllers\Storefront\AuthController as StorefrontAuthController;
use App\Http\Controllers\Storefront\SocialAuthController;

Route::prefix('v1/storefront/auth')->group(function () {
    Route::post('/login', [StorefrontAuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [StorefrontAuthController::class, 'register'])->middleware('throttle:5,1');
    
    // Social Login Routes
    Route::get('/social/{provider}/redirect', [SocialAuthController::class, 'redirectToProvider']);
    Route::get('/social/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
});

Route::middleware('auth:sanctum')->prefix('v1/storefront/auth')->group(function () {
    Route::post('/logout', [StorefrontAuthController::class, 'logout']);
    Route::get('/me', [StorefrontAuthController::class, 'me']);
    Route::get('/orders', [StorefrontAuthController::class, 'orders']);
});


// ==========================================
// Storefront Public Routes
// ==========================================
use App\Http\Controllers\Api\Storefront\StorefrontDataController;
use App\Http\Controllers\Api\Storefront\CheckoutController;
use App\Http\Controllers\Api\Storefront\ChunkUploadController;
use App\Http\Controllers\Storefront\CartRecoveryController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Api\Payment\PaymobWebhookController;
use App\Http\Controllers\Api\Storefront\PrintStationController;

Route::prefix('v1/storefront')->group(function () {
    Route::get('/categories', [StorefrontDataController::class, 'getCategories']);
    Route::get('/products', [StorefrontDataController::class, 'getProducts']);
    Route::get('/products/{id}', [StorefrontDataController::class, 'getProduct']);
    Route::get('/shipping-zones', [StorefrontDataController::class, 'getShippingZones']);
    Route::get('/checkout-settings', [StorefrontDataController::class, 'getCheckoutSettings']);
    Route::post('/checkout', [CheckoutController::class, 'process'])->middleware('throttle:5,1');
    Route::get('/orders/{id}/payment-status', [CheckoutController::class, 'getPaymentStatus']);
    Route::get('/orders/pay/{token}', [\App\Http\Controllers\Api\Storefront\OrderPaymentTokenController::class, 'showByToken']);
    Route::post('/orders/pay/{token}/initiate', [\App\Http\Controllers\Api\Storefront\OrderPaymentTokenController::class, 'initiatePayment'])->middleware('throttle:10,1');
    Route::post('/orders/pay/{token}/request-reprice', [\App\Http\Controllers\Api\Storefront\OrderPaymentTokenController::class, 'requestReprice'])->middleware('throttle:5,1');
    
    // Public Cart
    Route::get('/cart/recover/{uuid}', [CartRecoveryController::class, 'recover']);
    Route::post('/cart', [CartController::class, 'getOrCreate']);
    Route::post('/cart/contact', [CartController::class, 'captureContact']);
    Route::post('/cart/migrate', [CartController::class, 'migrate']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{itemId}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);
    Route::delete('/cart/coupon', [CartController::class, 'removeCoupon']);

    // Public Print Station
    Route::get('/printing-product-types', [PrintStationController::class, 'getPrintingProductTypes']);
    Route::get('/printing-types/{id}', [PrintStationController::class, 'getPrintingTypeDetails']);
    Route::post('/print/calculate-price', [PrintStationController::class, 'calculateLivePrice']);
    Route::post('/print/temp-upload', [PrintStationController::class, 'tempUpload']);
    Route::post('/print/chunk-upload', [ChunkUploadController::class, 'upload']);
    Route::post('/print/add-to-cart', [PrintStationController::class, 'addToCart']);
    Route::get('/print/file-status/{cartItemId}', [PrintStationController::class, 'checkFileStatus']);
    Route::delete('/print/files/{cartItemId}/{category}', [PrintStationController::class, 'deleteUnlinkedFile']);
    
    // Blog
    Route::get('/blog-posts', [App\Http\Controllers\Api\Storefront\BlogController::class, 'index']);
    Route::get('/blog-posts/{idOrSlug}', [App\Http\Controllers\Api\Storefront\BlogController::class, 'show']);
    
    // Offers
    Route::get('/offers', [App\Http\Controllers\Api\Storefront\OfferController::class, 'index']);

    // Addons
    Route::get('/addons/temp-file/{id}', [App\Http\Controllers\Api\Storefront\AddonFileController::class, 'getTempFile']);

    // Tracking
    Route::post('/track', [\App\Http\Controllers\Api\Storefront\TrackingController::class, 'track'])->middleware('throttle:60,1');

    // Storefront Chat & WhatsApp
    Route::prefix('chat')->group(function () {
        Route::get('/settings', [\App\Http\Controllers\Api\Storefront\ChatController::class, 'getSettings']);
        Route::get('/faqs', [\App\Http\Controllers\Api\Storefront\ChatController::class, 'getFaqs']);
        Route::post('/conversations', [\App\Http\Controllers\Api\Storefront\ChatController::class, 'getOrCreateConversation']);
        Route::get('/conversations/{uuid}/messages', [\App\Http\Controllers\Api\Storefront\ChatController::class, 'getMessages']);
        Route::post('/conversations/{uuid}/messages', [\App\Http\Controllers\Api\Storefront\ChatController::class, 'storeMessage'])->middleware('throttle:30,1');
    });
});

Route::prefix('v1/payment')->group(function () {
    Route::post('/kashier/webhook', [\App\Http\Controllers\Api\Payment\KashierWebhookController::class, 'handle'])
        ->middleware(\App\Http\Middleware\PaymentWebhookSecurityMiddleware::class);
    Route::post('/paymob/webhook', [\App\Http\Controllers\Api\Payment\PaymobWebhookController::class, 'handle'])
        ->middleware(\App\Http\Middleware\PaymentWebhookSecurityMiddleware::class);
});

Route::prefix('v1/shipping')->group(function () {
    Route::post('/webhook/{provider}', [\App\Http\Controllers\Api\Shipping\ShippingWebhookController::class, 'handle'])
        ->middleware(\App\Http\Middleware\ShippingWebhookSecurity::class);
});

Route::fallback(function (\Illuminate\Http\Request $request) {
    file_put_contents(storage_path('logs/fallback.log'), '[' . date('Y-m-d H:i:s') . '] 404 Fallback Hit! Method: ' . $request->method() . ', URL: ' . $request->fullUrl() . ', Headers: ' . json_encode($request->headers->all()) . PHP_EOL, FILE_APPEND);
    return response()->json(['error' => 'Fallback 404'], 404);
});
