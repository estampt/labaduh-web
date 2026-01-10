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

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Vendor application documents (vendor role; pending vendors allowed)
        Route::get('/vendor/documents', [VendorDocumentController::class, 'index']);
        Route::post('/vendor/documents', [VendorDocumentController::class, 'upsert']);
    });

    Route::get('/services', [ServiceCatalogController::class, 'index']);

    Route::get('/vendors', [VendorController::class, 'index']);
    Route::get('/vendors/{vendor}', [VendorController::class, 'show']);

    Route::get('/vendors/{vendor}/shops', [VendorShopController::class, 'index']);
    Route::get('/vendors/{vendor}/reviews', [VendorReviewController::class, 'index']);
    Route::get('/vendors/{vendor}/services', [VendorServiceController::class, 'list']);
    Route::post('/vendors/{vendor}/pricing/preview', [VendorServiceController::class, 'pricingPreview']);

    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    Route::middleware('auth:sanctum')->post('/orders/{order}/review', [VendorReviewController::class, 'storeForOrder']);

    Route::middleware(['auth:sanctum','vendor_or_admin','approved_vendor'])->group(function () {

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
    });

    Route::middleware(['auth:sanctum','admin_only'])->prefix('admin')->group(function () {
        Route::get('/vendors/pending', [AdminVendorApprovalController::class, 'pending']);
        Route::patch('/vendors/{vendor}/approve', [AdminVendorApprovalController::class, 'approve']);
        Route::patch('/vendors/{vendor}/reject', [AdminVendorApprovalController::class, 'reject']);

        // Vendor documents review
        Route::get('/vendors/{vendor}/documents', [AdminVendorDocumentController::class, 'listForVendor']);
        Route::patch('/vendor-documents/{document}/approve', [AdminVendorDocumentController::class, 'approve']);
        Route::patch('/vendor-documents/{document}/reject', [AdminVendorDocumentController::class, 'reject']);
    });
});
