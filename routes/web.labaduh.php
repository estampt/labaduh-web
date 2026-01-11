<?php
// Copy these routes into your routes/web.php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\VendorController as AdminVendorController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;

use App\Http\Controllers\Vendor\DashboardController as VendorDashboardController;
use App\Http\Controllers\Vendor\OrderController as VendorOrderController;
use App\Http\Controllers\Vendor\JobOfferController as VendorJobOfferController;
use App\Http\Controllers\Vendor\PricingController as VendorPricingController;
use App\Http\Controllers\Vendor\ShopController as VendorShopController;
use App\Http\Controllers\Vendor\SubscriptionController as VendorSubscriptionController;

Route::middleware(['auth', 'verified'])->group(function () {

    Route::prefix('admin')->middleware(['role:admin'])->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/vendors', [AdminVendorController::class, 'index'])->name('admin.vendors.index');
        Route::get('/vendors/{vendor}', [AdminVendorController::class, 'show'])->name('admin.vendors.show');
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
        Route::get('/pricing', [AdminPricingController::class, 'index'])->name('admin.pricing.index');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
        Route::get('/settings', [AdminSettingController::class, 'index'])->name('admin.settings.index');
    });

    Route::prefix('vendor')->middleware(['role:vendor'])->group(function () {
        Route::get('/dashboard', [VendorDashboardController::class, 'index'])->name('vendor.dashboard');
        Route::get('/orders', [VendorOrderController::class, 'index'])->name('vendor.orders.index');
        Route::get('/job-offers', [VendorJobOfferController::class, 'index'])->name('vendor.job_offers.index');
        Route::get('/pricing', [VendorPricingController::class, 'index'])->name('vendor.pricing.index');
        Route::get('/shops', [VendorShopController::class, 'index'])->name('vendor.shops.index');
        Route::get('/subscription', [VendorSubscriptionController::class, 'index'])->name('vendor.subscription.index');
    });
});
