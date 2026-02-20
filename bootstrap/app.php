<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',

        then: function () {
            // ✅ Load additional web routes for your web apps (admin/vendor)
            Route::middleware('web')->group(__DIR__ . '/../routes/app.php');
        }
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ✅ Force JSON for all API routes
        $middleware->appendToGroup('api', \App\Http\Middleware\ForceJsonResponse::class);

        // ✅ NEW: log ALL incoming + outgoing API JSON (request + response)
        // Put this early so you capture what happens even if later middleware/controllers fail.
        $middleware->appendToGroup('api', \App\Http\Middleware\LogJsonTraffic::class);

        // ✅ Your existing API logger (keep if you still want it)
        $middleware->appendToGroup('api', \App\Http\Middleware\ApiLogger::class);

        // ✅ NEW: update users.last_seen_at (throttled inside middleware)
        $middleware->appendToGroup('api', \App\Http\Middleware\UpdateLastSeen::class);

        // ✅ Register route middleware aliases (Laravel 11)
        $middleware->alias([
            'admin_only' => \App\Http\Middleware\AdminOnly::class,
            'admin'      => \App\Http\Middleware\AdminOnly::class, // ✅ add this
            'vendor_or_admin' => \App\Http\Middleware\VendorOrAdmin::class,
            'approved_vendor' => \App\Http\Middleware\ApprovedVendor::class,
            'vendor_owns_vendor' => \App\Http\Middleware\VendorOwnsVendor::class,
            'vendor_owns_shop' => \App\Http\Middleware\VendorOwnsShop::class,
            'customer' => \App\Http\Middleware\CustomerOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
