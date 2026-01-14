<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // OTP verification (token-based)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Email OTP
        Route::post('/auth/send-email-otp', [AuthController::class, 'sendEmailOtp'])->middleware('throttle:5,1');
        Route::post('/auth/verify-email-otp', [AuthController::class, 'verifyEmailOtp']);

        // SMS placeholder
        Route::post('/auth/send-phone-otp', [AuthController::class, 'sendPhoneOtp'])->middleware('throttle:3,1');
        Route::post('/auth/verify-phone-otp', [AuthController::class, 'verifyPhoneOtp']);
    });
});
