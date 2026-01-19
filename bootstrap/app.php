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

        // ✅ Your existing API logger
        $middleware->appendToGroup('api', \App\Http\Middleware\ApiLogger::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
