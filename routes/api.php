<?php

use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\UpdateController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\V3\CategoryController;
use App\Http\Controllers\Api\V3\CouponController;
use App\Http\Controllers\Api\V3\MarketplaceController;
use App\Http\Controllers\Api\V3\PurchaseController;
use App\Http\Controllers\Api\V3\RefundController;
use App\Http\Controllers\Api\V3\ReviewController;
use App\Http\Controllers\Api\V3\SearchController;
use App\Http\Controllers\Api\V3\AdController;
use App\Http\Controllers\Api\V3\PublicApiController;
use App\Http\Controllers\Api\V4\TaxController;
use App\Http\Controllers\Api\V4\LedgerController;
use App\Http\Controllers\Api\V4\RegionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V2 Routes - LicenseOS V2.1
|--------------------------------------------------------------------------
*/

// === Public API (no auth required) ===

// Payment webhooks (called by payment providers)
Route::prefix('v2')->group(function () {

    // Stripe webhook
    Route::post('/webhook/stripe', [WebhookController::class, 'stripe']);

    // WeChat Pay webhook
    Route::post('/webhook/wechat', [WebhookController::class, 'wechat']);

    // Alipay webhook
    Route::post('/webhook/alipay', [WebhookController::class, 'alipay']);

    // Simulated payment (for testing)
    Route::post('/webhook/payment', [WebhookController::class, 'simulatedPayment']);

    // Checkout session creation (can be called from storefront, rate limited)
    Route::post('/checkout/create', [CheckoutController::class, 'create'])->middleware('throttle:5,10');

    // Secure download (license key verified inside controller, no api.key middleware needed)
    Route::get('/download', [UpdateController::class, 'download'])->middleware('throttle:10,1');
});

// === Protected API (API Key required) ===

Route::prefix('v2')->middleware('api.key')->group(function () {

    // License activation
    Route::post('/license/activate', [LicenseController::class, 'activate']);

    // License check
    Route::post('/license/check', [LicenseController::class, 'check']);

    // Integrity report (code tamper detection)
    Route::post('/integrity/report', [\App\Http\Controllers\Api\IntegrityController::class, 'report']);

    // SDK calls this to authorize mu-plugin deletion
    Route::post('/license/remove-guardian', [LicenseController::class, 'removeGuardian']);

    // Update check (for WordPress plugins / SDK)
    Route::get('/update/check', [UpdateController::class, 'check']);
});

/*
|--------------------------------------------------------------------------
| API V3 Routes - LicenseOS V3 Marketplace
|--------------------------------------------------------------------------
*/

// === Public Marketplace (no auth required) ===
Route::prefix('v3')->group(function () {

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);

    // Products listing
    Route::get('/products', [MarketplaceController::class, 'index']);

    // Featured products
    Route::get('/products/featured', [MarketplaceController::class, 'featured']);

    // Search products
    Route::get('/search', [SearchController::class, 'index']);

    // Product detail by slug
    Route::get('/products/{slug}', [MarketplaceController::class, 'show']);

    // Product reviews (approved only)
    Route::get('/products/{slug}/reviews', [ReviewController::class, 'index']);

    // Coupons
    Route::post('/coupons/validate', [CouponController::class, 'validate']);
    Route::get('/coupons/available', [CouponController::class, 'available']);
});

// === Authenticated V3 endpoints ===
Route::prefix('v3')->middleware('auth:sanctum')->group(function () {

    // Submit a review
    Route::post('/products/{slug}/reviews', [ReviewController::class, 'store']);

    // Purchase (create checkout session)
    Route::post('/purchase', [PurchaseController::class, 'store']);

    // List user's orders
    Route::get('/orders', [PurchaseController::class, 'index']);

    // Get order detail
    Route::get('/orders/{orderId}', [PurchaseController::class, 'show']);

    // Request refund
    Route::post('/orders/{orderId}/refund', [RefundController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| API V3.4 Routes - Commercial Expansion
|--------------------------------------------------------------------------
*/

// === Public API with quota tracking (V3.4 Open Platform) ===
Route::prefix('v3/public')->middleware('api.quota')->group(function () {
    Route::get('/products', [PublicApiController::class, 'products']);
    Route::get('/search', [PublicApiController::class, 'search']);
    Route::post('/license/check', [PublicApiController::class, 'licenseCheck']);
});

// === Ads (public) ===
Route::prefix('v3/ads')->group(function () {
    Route::get('/banners', [AdController::class, 'banners']);
    Route::get('/featured', [AdController::class, 'featured']);
    Route::post('/{ad}/click', [AdController::class, 'recordClick']);
});

// === Ads management (authenticated developers) ===
Route::prefix('v3/ads')->middleware('auth:sanctum')->group(function () {
    Route::post('/campaigns', [AdController::class, 'createCampaign']);
    Route::get('/my-campaigns', [AdController::class, 'myCampaigns']);
    Route::post('/{ad}/pause', [AdController::class, 'pause']);
    Route::post('/{ad}/activate', [AdController::class, 'activate']);
    Route::get('/stats', [AdController::class, 'stats']);
});

/*
|--------------------------------------------------------------------------
| API V4 Routes - Global Architecture
|--------------------------------------------------------------------------
*/

// === V4 Public endpoints ===
Route::prefix('v4')->group(function () {
    // Tax calculation (public)
    Route::post('/tax/calculate', [TaxController::class, 'calculate']);
    Route::get('/tax/rules', [TaxController::class, 'rules']);

    // Region info (public)
    Route::get('/regions', [RegionController::class, 'index']);
    Route::get('/regions/resolve', [RegionController::class, 'resolve']);
});

// === V4 Authenticated endpoints ===
Route::prefix('v4')->middleware('auth:sanctum')->group(function () {
    // Multi-currency ledger
    Route::get('/ledger', [LedgerController::class, 'index']);
    Route::get('/ledger/balance', [LedgerController::class, 'balance']);
});

