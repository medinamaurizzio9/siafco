<?php

use App\Http\Controllers\Api\Mobile\V1\AuthController;
use App\Http\Controllers\Api\Mobile\V1\AffiliationController;
use App\Http\Controllers\Api\Mobile\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->name('api.mobile.v1.')->group(function () {
    Route::get('/catalogs', [AffiliationController::class, 'catalogs'])
        ->middleware('throttle:30,1')
        ->name('catalogs');

    Route::post('/affiliation-requests', [AffiliationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('affiliation-requests.store');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'mobile.affiliate'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('/me/profile', [ProfileController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('me.profile.update');
        Route::post('/me/profile/photo', [ProfileController::class, 'updatePhoto'])
            ->middleware('throttle:3,1')
            ->name('me.profile.photo.update');
        Route::patch('/me/password', [ProfileController::class, 'updatePassword'])
            ->middleware('throttle:5,1')
            ->name('me.password.update');
        Route::get('/me/affiliation-request', [AffiliationController::class, 'show'])
            ->name('me.affiliation-request.show');
        Route::post('/me/affiliation-request/payment', [AffiliationController::class, 'submitPayment'])
            ->middleware('throttle:5,1')
            ->name('me.affiliation-request.payment.store');
    });
});
