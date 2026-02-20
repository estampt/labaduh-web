<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerBroadcastController;
use App\Http\Controllers\Api\V1\DriverDeliveryController;
use App\Http\Controllers\Api\V1\JobRequestController;
use App\Http\Controllers\Api\V1\NotificationInboxController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\ServiceCatalogController;
use App\Http\Controllers\Api\V1\ShopAvailabilityController;
use App\Http\Controllers\Api\V1\VendorController;
use App\Http\Controllers\Api\V1\VendorDocumentController;
use App\Http\Controllers\Api\V1\VendorJobController;
use App\Http\Controllers\Api\V1\VendorPricingController;
use App\Http\Controllers\Api\V1\VendorReviewController;
use App\Http\Controllers\Api\V1\VendorServiceController;
use App\Http\Controllers\Api\V1\VendorShopController;
use App\Http\Controllers\Api\V1\ShopServiceController;
use App\Http\Controllers\Api\V1\ShopServiceOptionController;

use App\Http\Controllers\Api\V1\CustomerOrderPricingController;
use App\Http\Controllers\Api\V1\CustomerDiscoveryServiceController;
use App\Http\Controllers\Api\V1\CustomerQuoteController;
use App\Http\Controllers\Api\V1\CustomerOrderController;
use App\Http\Controllers\Api\V1\CustomerOrderTimelineController;
use App\Http\Controllers\Api\V1\VendorOrderPricingController;
use App\Http\Controllers\Api\V1\VendorOrderController;
use App\Http\Controllers\Api\V1\VendorOrderBroadcastController;

use App\Http\Controllers\Api\V1\AdminAddonController;
use App\Http\Controllers\Api\V1\AdminPushTestController;
use App\Http\Controllers\Api\V1\AdminServiceController;
use App\Http\Controllers\Api\V1\AdminServiceOptionController;
use App\Http\Controllers\Api\V1\AdminVendorApprovalController;
use App\Http\Controllers\Api\V1\AdminVendorDocumentController;

use App\Http\Controllers\Api\V1\FulfillmentController;

use App\Http\Controllers\Api\V1\AppSettingsController;
use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\MeController;


use App\Models\PushToken;

Route::prefix('v1')->group(function () {


 Route::get('/broadcast-run/{id}', function ($id) {
            $job = new \App\Jobs\SendOrderBroadcastPushJob((int)$id);
            $job->handle(app(\App\Services\PushNotificationService::class));

            return response()->json(['ok' => true, 'ran' => (int)$id]);
        });


    /**
     * Public (no auth)
     */
    Route::post('/webhooks/paymongo', [PaymentWebhookController::class, 'paymongo']);

    Route::prefix('auth')->group(function () {




        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Email OTP
        Route::post('/send-email-otp', [AuthController::class, 'sendEmailOtp'])->middleware('throttle:5,1');
        Route::post('/verify-email-otp', [AuthController::class, 'verifyEmailOtp'])->middleware('auth:sanctum');;

        // SMS placeholder
        Route::post('/send-phone-otp', [AuthController::class, 'sendPhoneOtp'])->middleware('throttle:3,1');
        Route::post('/verify-phone-otp', [AuthController::class, 'verifyPhoneOtp']);
    });

    // Catalog / discovery
    Route::get('/services', [ServiceCatalogController::class, 'index']);
    Route::get('/vendors', [VendorController::class, 'index']);
    Route::get('/vendors/{vendor}', [VendorController::class, 'show']);
    Route::get('/vendors/{vendor}/shops', [VendorShopController::class, 'index']);
    Route::get('/vendors/{vendor}/reviews', [VendorReviewController::class, 'index']);
    Route::get('/vendors/{vendor}/services', [VendorServiceController::class, 'list']);
    Route::post('/vendors/{vendor}/pricing/preview', [VendorServiceController::class, 'pricingPreview']);

    // Shop availability
    Route::get('/shops/{shop}/capacity', [ShopAvailabilityController::class, 'capacity']);
    Route::get('/shops/{shop}/slots', [ShopAvailabilityController::class, 'slots']);

    // Orders (public create + view)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);


    //Route::get('/notifications/ops', [NotificationController::class, 'ops']);
    //Route::get('/notifications/chat', [NotificationController::class, 'chat']);
    //TODO: To decide what kind of notification option we have

    /**
     * Authenticated (any logged-in user)
     */
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/notifications', [NotificationInboxController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationInboxController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationInboxController::class, 'markAllRead']);
        Route::get('/notifications/unread-count', [NotificationInboxController::class, 'unreadCount']);

        // Auth session
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Vendor job offers
        Route::get('/vendor/job-offers', [VendorJobController::class, 'index']);
        Route::post('/vendor/job-offers/{offer}/accept', [VendorJobController::class, 'accept']);
        Route::post('/vendor/job-offers/{offer}/reject', [VendorJobController::class, 'reject']);

        // Driver deliveries
        Route::get('/driver/deliveries', [DriverDeliveryController::class, 'myDeliveries']);
        Route::patch('/driver/deliveries/{delivery}/status', [DriverDeliveryController::class, 'updateStatus']);

        // Vendor application documents (vendor role; pending vendors allowed)
        Route::get('/vendor/documents', [VendorDocumentController::class, 'index']);
        Route::post('/vendor/documents', [VendorDocumentController::class, 'upsert']);

        // Vendor pricing (general)
        Route::get('/vendor/pricing', [VendorPricingController::class, 'index']);
        Route::post('/vendor/pricing/service-prices', [VendorPricingController::class, 'upsertServicePrices']);
        Route::delete('/vendor/pricing/shop-price/{id}', [VendorPricingController::class, 'deleteShopPrice']);
        Route::post('/vendor/pricing/delivery-price', [VendorPricingController::class, 'upsertDeliveryPrice']);

        // Payments / fulfillment
        Route::post('/orders/{order}/payment-intent', [PaymentController::class, 'createOrderPaymentIntent']);
        Route::patch('/orders/{order}/fulfillment', [FulfillmentController::class, 'set']);

        // Reviews
        Route::post('/orders/{order}/review', [VendorReviewController::class, 'storeForOrder']);

        // Job request flow
        Route::post('/job-requests/match', [JobRequestController::class, 'createAndMatch']);
        Route::post('/job-requests/{jobRequest}/broadcast', [CustomerBroadcastController::class, 'broadcast']);

        Route::post('/me/last-seen', [MeController::class, 'lastSeen']);

        // Push / notifications (keep public if you want — you can move under auth later)
        Route::post('/push/token', [PushTokenController::class, 'store']);
    });


    /************
     * Customer APIS
    */
    Route::middleware(['auth:sanctum', 'customer'])->group(function () {

        Route::prefix('customer')->group(function () {

            // Customer order history
            Route::get('orders', [CustomerOrderController::class, 'index']);
            // supports ?status=&cursor=&per_page=

            // Latest order shortcut
            Route::get('orders/latest', [CustomerOrderController::class, 'latest']);



            Route::post('orders', [CustomerOrderController::class, 'store']);

            Route::get('discovery/services', CustomerDiscoveryServiceController::class);
            Route::post('quotes', [CustomerQuoteController::class, 'store']);
            Route::get('orders/{order}', [CustomerOrderController::class, 'show']);

            Route::get('orders/by_id/{order_id}', [CustomerOrderController::class, 'getOrderById']);

            Route::post('orders/{order}/approve-final', [CustomerOrderPricingController::class, 'approveFinal']);
            Route::post('orders/{order}/reject-final', [CustomerOrderPricingController::class, 'rejectFinal']);

            Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancelOrder']);

            Route::post('/orders/{order}/confirm-delivery', [CustomerOrderController::class, 'confirmDelivery']);
            Route::post('/orders/{order}/feedback', [CustomerOrderController::class, 'feedback']);

        });

    });


    /************
     * End of Customer APIs
    */


    /**
     * Vendor / Admin (approved vendor)
     */
    Route::middleware(['auth:sanctum', 'vendor_or_admin', 'approved_vendor'])->group(function () {

        // Vendor-only vendor/shop management (with ownership middleware)
        Route::get('/vendors/{vendor}/shops', [VendorShopController::class, 'index'])
            ->middleware('vendor_owns_vendor');

        Route::put('/vendors/{vendor}', [VendorController::class, 'update'])
            ->middleware('vendor_owns_vendor');

        Route::post('/vendors/{vendor}/shops', [VendorShopController::class, 'store'])
            ->middleware('vendor_owns_vendor');

        Route::put('/vendors/{vendor}/shops/{shop}', [VendorShopController::class, 'update'])
            ->middleware(['vendor_owns_vendor', 'vendor_owns_shop']);

        Route::post('/vendors/{vendor}/shops/{shop}/photo', [VendorShopController::class, 'uploadPhoto'])
            ->middleware(['vendor_owns_vendor', 'vendor_owns_shop']);

        Route::patch('/vendors/{vendor}/shops/{shop}/toggle', [VendorShopController::class, 'toggle'])
            ->middleware(['vendor_owns_vendor', 'vendor_owns_shop']);

        Route::post('/vendors/{vendor}/services', [VendorServiceController::class, 'upsert'])
            ->middleware('vendor_owns_vendor');

        Route::patch('/vendors/{vendor}/services/{vendorService}/toggle', [VendorServiceController::class, 'toggle'])
            ->middleware('vendor_owns_vendor');

        // Orders
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

        // Service list (your admin-service controller endpoint kept here as you had it)
        Route::get('/services', [AdminServiceController::class, 'index']);

        /**
         * Vendor Shop Service Prices CRUD
         */
        Route::prefix('/vendors/{vendor}/shops/{shop}')->middleware(['vendor_owns_vendor', 'vendor_owns_shop'])->group(function () {

            //Added by Rehnee on 31-Jan-2026
            //New Services Model
            Route::get   ('services',               [ShopServiceController::class, 'index']);
            Route::post  ('services',               [ShopServiceController::class, 'store']);
            Route::get   ('services/{shopService}', [ShopServiceController::class, 'show']);
            Route::put   ('services/{shopService}', [ShopServiceController::class, 'update']);
            Route::delete('services/{shopService}', [ShopServiceController::class, 'destroy']);

            //Added by Rehnee on 31-Jan-2026
            //New Service options
            Route::get   ('services/{shopService}/options',                  [ShopServiceOptionController::class, 'index']);
            Route::post  ('services/{shopService}/options',                  [ShopServiceOptionController::class, 'store']);
            Route::get   ('services/{shopService}/options/{shopServiceOption}', [ShopServiceOptionController::class, 'show']);
            Route::put   ('services/{shopService}/options/{shopServiceOption}', [ShopServiceOptionController::class, 'update']);
            Route::delete('services/{shopService}/options/{shopServiceOption}', [ShopServiceOptionController::class, 'destroy']);




            Route::get('/orders', [VendorOrderController::class, 'getActiveOrderbyShop']);


            // Vendor order status actions
            Route::post('/orders/{order}/pickup_scheduled', [VendorOrderController::class, 'pickupScheduled']);
            Route::post('/orders/{order}/picked_up', [VendorOrderController::class, 'pickedUp']);

            // ✅ Weight flow
            Route::post('/orders/{order}/weight_reviewed', [VendorOrderController::class, 'weightReviewed']);
            Route::post('/orders/{order}/weight_accepted', [VendorOrderController::class, 'weightAccepted']);

            Route::post('/orders/{order}/washing', [VendorOrderController::class, 'startWashing']);
            Route::post('/orders/{order}/ready', [VendorOrderController::class, 'ready']);

            // ✅ Delivery flow
            Route::post('/orders/{order}/delivery_scheduled', [VendorOrderController::class, 'deliveryScheduled']);
            Route::post('/orders/{order}/out_for_delivery', [VendorOrderController::class, 'outForDelivery']);

            Route::post('/orders/{order}/delivered', [VendorOrderController::class, 'delivered']);
            Route::post('/orders/{order}/completed', [VendorOrderController::class, 'completed']);

            // routes/api.php


            Route::get('/orders/active-summary',[VendorOrderController::class, 'activeSummaryByShop']);


             // Retrieve Order
            Route::get('/orders/broadcasted_by_order_id', [VendorOrderBroadcastController::class, 'getBroadCastedOrderByOrderId']);


            Route::get('/orders/broadcast_by_id', [VendorOrderBroadcastController::class, 'getBroadcastById']);

            // Accept a broadcast (claim the order)
            Route::post('/order-broadcasts/{broadcast}/accept', [VendorOrderBroadcastController::class, 'accept']);

            // Reject a broadcast (claim the order)
            Route::post('/order-broadcasts/{broadcast}/reject', [VendorOrderBroadcastController::class, 'reject']);


            Route::get('/orders/broadcasted',[VendorOrderBroadcastController::class, 'getBroadcastedOrderHeadersByShop']);


            // ✅ Repricing proposal
            Route::post('/orders/{order}/propose-final', [VendorOrderPricingController::class, 'proposeFinal']);



            //TODO : For 3rd party driver
//            Route::post('/orders/{order}/pickup-assigned', [DriverOrderStatusController::class, 'pickupAssigned']);
//            Route::post('/orders/{order}/picked-up', [DriverOrderStatusController::class, 'pickedUpFromCustomer']);

//            Route::post('/orders/{order}/delivery-assigned', [DriverOrderStatusController::class, 'deliveryAssigned']);
//            Route::post('/orders/{order}/out-for-delivery', [DriverOrderStatusController::class, 'outForDelivery']);
//            Route::post('/orders/{order}/delivered', [DriverOrderStatusController::class, 'delivered']);


        });

        // Master list for picker (if you don’t have yet)
        Route::get('/service-options', [AdminServiceOptionController::class, 'index']);
    });


    /**
     * Admin only
     */
    Route::middleware(['auth:sanctum', 'admin_only'])->prefix('admin')->group(function () {

        // Vendors approval
        Route::get('/vendors', [AdminVendorApprovalController::class, 'index']);
        Route::get('/vendors/pending', [AdminVendorApprovalController::class, 'pending']);
        Route::get('/vendors/{vendor}', [AdminVendorApprovalController::class, 'show']);
        Route::patch('/vendors/{vendor}/approve', [AdminVendorApprovalController::class, 'approve']);
        Route::patch('/vendors/{vendor}/reject', [AdminVendorApprovalController::class, 'reject']);

        // Add-ons catalog
        Route::get('/addons', [AdminAddonController::class, 'index']);
        Route::post('/addons', [AdminAddonController::class, 'store']);
        Route::patch('/addons/{addon}', [AdminAddonController::class, 'update']);
        Route::delete('/addons/{addon}', [AdminAddonController::class, 'destroy']);

        // Service options
        Route::get('/service-options', [AdminServiceOptionController::class, 'index']);
        Route::post('/service-options', [AdminServiceOptionController::class, 'store']);
        Route::get('/service-options/{serviceOption}', [AdminServiceOptionController::class, 'show']);
        Route::put('/service-options/{serviceOption}', [AdminServiceOptionController::class, 'update']);
        Route::delete('/service-options/{serviceOption}', [AdminServiceOptionController::class, 'destroy']);
        Route::patch('/service-options/{serviceOption}/toggle', [AdminServiceOptionController::class, 'toggleActive']);

        // Vendor documents review
        Route::get('/vendors/{vendor}/documents', [AdminVendorDocumentController::class, 'listForVendor']);
        Route::patch('/vendor-documents/{document}/approve', [AdminVendorDocumentController::class, 'approve']);
        Route::patch('/vendor-documents/{document}/reject', [AdminVendorDocumentController::class, 'reject']);

        // Services (admin CRUD)
        Route::get('/services', [AdminServiceController::class, 'index']);
        Route::post('/services', [AdminServiceController::class, 'store']);
        Route::put('/services/{service}', [AdminServiceController::class, 'update']);
        Route::delete('/services/{service}', [AdminServiceController::class, 'destroy']);
        Route::patch('/services/{service}/active', [AdminServiceController::class, 'setActive']);

        // Notifications
        Route::post('/push/test', [AdminPushTestController::class, 'send']);

        //App Configuration
        Route::get('app-config', [AppConfigController::class, 'show']);

        Route::get('app-settings', [AppSettingsController::class, 'index']);
        Route::post('app-settings', [AppSettingsController::class, 'upsert']);

    });
});
