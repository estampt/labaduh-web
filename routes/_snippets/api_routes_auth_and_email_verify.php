<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed'])
        ->name('api.verification.verify');

    Route::middleware('auth:sanctum')->post('/auth/email/resend', [AuthController::class, 'resendVerification']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });
});
