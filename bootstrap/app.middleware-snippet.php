<?php
// Add this inside Application::configure(...)->withMiddleware(function (Middleware $middleware) { ... })
//
// ->withMiddleware(function (\Illuminate\Foundation\Configuration\Middleware $middleware) {
//     $middleware->alias([
//         'vendor_or_admin' => \App\Http\Middleware\EnsureVendorOrAdmin::class,
//         'vendor_owns_vendor' => \App\Http\Middleware\EnsureVendorOwnsVendor::class,
//         'approved_vendor' => \App\Http\Middleware\EnsureApprovedVendor::class,
//         'admin_only' => \App\Http\Middleware\EnsureAdminOnly::class,
//         'vendor_owns_shop' => \App\Http\Middleware\EnsureVendorOwnsShop::class,
//     ]);
// })
//
