<?php

use App\Http\Controllers\Api\V1\AuthController;

Route::prefix('v1')->group(function () {
    // Accepts both customer and vendor registration
    Route::post('/auth/register', [AuthController::class, 'register']);
});
