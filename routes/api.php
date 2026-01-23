<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\VendorController;
use App\Http\Controllers\Api\V1\VendorShopController;
use App\Http\Controllers\Api\V1\ServiceCatalogController;
use App\Http\Controllers\Api\V1\VendorServiceController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\AdminVendorApprovalController;
use App\Http\Controllers\Api\V1\VendorReviewController;
use App\Http\Controllers\Api\V1\VendorDocumentController;
use App\Http\Controllers\Api\V1\AdminVendorDocumentController;
use App\Http\Controllers\Api\V1\ShopAvailabilityController;
use App\Http\Controllers\Api\V1\JobRequestController;
use App\Http\Controllers\Api\V1\DriverDeliveryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\VendorPricingController;
use App\Http\Controllers\Api\V1\CustomerBroadcastController;
use App\Http\Controllers\Api\V1\VendorJobController;
use App\Http\Controllers\Api\V1\AdminAddonController;


Route::prefix('v1')->group(function () {
    // Payment webhooks (no auth)
    Route::post('/webhooks/paymongo', [PaymentWebhookController::class, 'paymongo']);

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);


    Route::middleware('auth:sanctum')->group(function () {
        // Vendor job offers
        Route::get('/vendor/job-offers', [VendorJobController::class, 'index']);
        Route::post('/vendor/job-offers/{offer}/accept', [VendorJobController::class, 'accept']);
        Route::post('/vendor/job-offers/{offer}/reject', [VendorJobController::class, 'reject']);

        // Driver deliveries
        Route::get('/driver/deliveries', [DriverDeliveryController::class, 'myDeliveries']);
        Route::patch('/driver/deliveries/{delivery}/status', [DriverDeliveryController::class, 'updateStatus']);

        // Vendor pricing
        Route::get('/vendor/pricing', [VendorPricingController::class, 'index']);
        Route::post('/vendor/pricing/service-prices', [VendorPricingController::class, 'upsertServicePrices']);
        Route::delete('/vendor/pricing/shop-price/{id}', [VendorPricingController::class, 'deleteShopPrice']);

        Route::post('/vendor/pricing/delivery-price', [VendorPricingController::class, 'upsertDeliveryPrice']);

        // Payments
        Route::post('/orders/{order}/payment-intent', [PaymentController::class, 'createOrderPaymentIntent']);
        Route::patch('/orders/{order}/fulfillment', [\App\Http\Controllers\Api\V1\FulfillmentController::class, 'set']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Vendor application documents (vendor role; pending vendors allowed)
        Route::get('/vendor/documents', [VendorDocumentController::class, 'index']);
        Route::post('/vendor/documents', [VendorDocumentController::class, 'upsert']);

        // Email OTP
        Route::post('/auth/send-email-otp', [AuthController::class, 'sendEmailOtp'])->middleware('throttle:5,1');
        Route::post('/auth/verify-email-otp', [AuthController::class, 'verifyEmailOtp']);

        // SMS placeholder
        Route::post('/auth/send-phone-otp', [AuthController::class, 'sendPhoneOtp'])->middleware('throttle:3,1');
        Route::post('/auth/verify-phone-otp', [AuthController::class, 'verifyPhoneOtp']);
    });

    Route::get('/services', [ServiceCatalogController::class, 'index']);

    Route::get('/vendors', [VendorController::class, 'index']);
    Route::get('/vendors/{vendor}', [VendorController::class, 'show']);

    Route::get('/vendors/{vendor}/shops', [VendorShopController::class, 'index']);
    Route::get('/vendors/{vendor}/reviews', [VendorReviewController::class, 'index']);

    // Shop availability
    Route::get('/shops/{shop}/capacity', [ShopAvailabilityController::class, 'capacity']);
    Route::get('/shops/{shop}/slots', [ShopAvailabilityController::class, 'slots']);
    Route::get('/vendors/{vendor}/services', [VendorServiceController::class, 'list']);
    Route::post('/vendors/{vendor}/pricing/preview', [VendorServiceController::class, 'pricingPreview']);

    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    Route::middleware('auth:sanctum')->post('/orders/{order}/review', [VendorReviewController::class, 'storeForOrder']);

    // Customer creates request then sees ranked vendors
    Route::middleware('auth:sanctum')->post('/job-requests/match', [JobRequestController::class, 'createAndMatch']);
    Route::middleware('auth:sanctum')->post('/job-requests/{jobRequest}/broadcast', [CustomerBroadcastController::class, 'broadcast']);

    Route::middleware(['auth:sanctum','vendor_or_admin','approved_vendor'])->group(function () {

        Route::get('/vendors/{vendor}/shops', [VendorShopController::class, 'index'])
        ->middleware('vendor_owns_vendor');


        Route::put('/vendors/{vendor}', [VendorController::class, 'update'])
            ->middleware('vendor_owns_vendor');

        Route::post('/vendors/{vendor}/shops', [VendorShopController::class, 'store'])
            ->middleware('vendor_owns_vendor');
        Route::put('/vendors/{vendor}/shops/{shop}', [VendorShopController::class, 'update'])
            ->middleware(['vendor_owns_vendor','vendor_owns_shop']);
        Route::patch('/vendors/{vendor}/shops/{shop}/toggle', [VendorShopController::class, 'toggle'])
            ->middleware(['vendor_owns_vendor','vendor_owns_shop']);

        Route::post('/vendors/{vendor}/services', [VendorServiceController::class, 'upsert'])
            ->middleware('vendor_owns_vendor');

        Route::patch('/vendors/{vendor}/services/{vendorService}/toggle', [VendorServiceController::class, 'toggle'])
            ->middleware('vendor_owns_vendor');

        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

        Route::get('/vendor/pricing', [VendorPricingController::class, 'index']);
        Route::post('/vendor/pricing/service-prices', [VendorPricingController::class, 'upsertServicePrices']);
        Route::delete('/vendor/pricing/shop-price/{id}', [VendorPricingController::class, 'deleteShopPrice']);

    });

    Route::middleware(['auth:sanctum','admin_only'])->prefix('admin')->group(function () {

        Route::get('/vendors', [AdminVendorApprovalController::class, 'index']);
        Route::get('/vendors/{vendor}', [AdminVendorApprovalController::class, 'show']);

        Route::get('/vendors/pending', [AdminVendorApprovalController::class, 'pending']);
        Route::patch('/vendors/{vendor}/approve', [AdminVendorApprovalController::class, 'approve']);
        Route::patch('/vendors/{vendor}/reject', [AdminVendorApprovalController::class, 'reject']);

        // Add-on catalog
        Route::get('/addons', [AdminAddonController::class, 'index']);
        Route::post('/addons', [AdminAddonController::class, 'store']);
        Route::patch('/addons/{addon}', [AdminAddonController::class, 'update']);
        Route::delete('/addons/{addon}', [AdminAddonController::class, 'destroy']);


        // Vendor documents review
        Route::get('/vendors/{vendor}/documents', [AdminVendorDocumentController::class, 'listForVendor']);
        Route::patch('/vendor-documents/{document}/approve', [AdminVendorDocumentController::class, 'approve']);
        Route::patch('/vendor-documents/{document}/reject', [AdminVendorDocumentController::class, 'reject']);

        Route::get('/services', [\App\Http\Controllers\Api\V1\AdminServiceController::class, 'index']);
        Route::post('/services', [\App\Http\Controllers\Api\V1\AdminServiceController::class, 'store']);
        Route::put('/services/{service}', [\App\Http\Controllers\Api\V1\AdminServiceController::class, 'update']);
        Route::delete('/services/{service}', [\App\Http\Controllers\Api\V1\AdminServiceController::class, 'destroy']);
        Route::patch('/services/{service}/active', [\App\Http\Controllers\Api\V1\AdminServiceController::class, 'setActive']);
    });
});
