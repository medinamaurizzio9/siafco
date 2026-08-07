<?php

use App\Http\Controllers\Api\Mobile\V1\AuthController;
use App\Http\Controllers\Api\Mobile\V1\AffiliationController;
use App\Http\Controllers\Api\Mobile\V1\CredentialController;
use App\Http\Controllers\Api\Mobile\V1\ProfileController;
use App\Http\Controllers\Api\Mobile\V1\Store\CatalogController as MobileStoreCatalogController;
use App\Http\Controllers\Api\Mobile\V1\Store\OrderController as MobileStoreOrderController;
use App\Http\Controllers\Api\Mobile\V1\Store\ReceiptController as MobileStoreReceiptController;
use App\Http\Controllers\Api\Mobile\V1\Store\WhatsAppController as MobileStoreWhatsAppController;
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
        Route::get('/me/payments', [ProfileController::class, 'payments'])
            ->middleware('throttle:30,1')
            ->name('me.payments.index');
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
        Route::get('/me/credential', [CredentialController::class, 'show'])
            ->middleware('throttle:20,1')
            ->name('me.credential.show');
        Route::post('/me/affiliation-request/payment', [AffiliationController::class, 'submitPayment'])
            ->middleware('throttle:5,1')
            ->name('me.affiliation-request.payment.store');

        Route::prefix('store')->name('store.')->middleware('mobile.affiliate.active')->group(function () {
            Route::get('/', [MobileStoreCatalogController::class, 'index'])
                ->middleware('throttle:60,1')
                ->name('index');
            Route::get('/delivery-destinations', [MobileStoreCatalogController::class, 'deliveryDestinations'])
                ->middleware('throttle:60,1')
                ->name('delivery-destinations.index');
            Route::get('/products/{productPublicCode}', [MobileStoreCatalogController::class, 'show'])
                ->middleware('throttle:60,1')
                ->name('products.show');
            Route::post('/quote', [MobileStoreCatalogController::class, 'quote'])
                ->middleware('throttle:20,1')
                ->name('quote');
            Route::post('/orders', [MobileStoreOrderController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('orders.store');
            Route::get('/orders', [MobileStoreOrderController::class, 'index'])
                ->middleware('throttle:60,1')
                ->name('orders.index');
            Route::get('/orders/{orderCode}', [MobileStoreOrderController::class, 'show'])
                ->middleware('throttle:60,1')
                ->name('orders.show');
            Route::post('/orders/{orderCode}/receipt', [MobileStoreReceiptController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('orders.receipt.store');
            Route::post('/orders/{orderCode}/whatsapp', [MobileStoreWhatsAppController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('orders.whatsapp.store');
        });
    });
});
