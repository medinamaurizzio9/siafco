<?php

use App\Http\Controllers\Api\Mobile\V1\AuthController;
use App\Http\Controllers\Api\Mobile\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->name('api.mobile.v1.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'mobile.affiliate'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('/me/profile', [ProfileController::class, 'update'])->name('me.profile.update');
        Route::patch('/me/password', [ProfileController::class, 'updatePassword'])
            ->middleware('throttle:5,1')
            ->name('me.password.update');
    });
});
