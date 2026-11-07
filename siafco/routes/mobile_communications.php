<?php

use App\Http\Controllers\CommunicationInboxController;
use App\Http\Middleware\CommunicationMobileAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->name('api.mobile.v1.')->middleware(['auth:sanctum', 'mobile.affiliate', CommunicationMobileAccess::class, 'throttle:60,1'])->controller(CommunicationInboxController::class)->group(function () {
    Route::get('communications', 'index')->name('communications.index');
    Route::get('communications/unread-count', 'count')->name('communications.count');
    Route::get('communications/{id}', 'show')->whereNumber('id')->name('communications.show');
    Route::post('communications/{id}/read', 'read')->whereNumber('id')->name('communications.read');
    Route::get('communications/{id}/attachments/{file}', 'file')->whereNumber(['id', 'file'])->name('communications.file');
    Route::post('push-devices', 'device')->name('devices.store');
    Route::delete('push-devices/{device}', 'revoke')->whereUuid('device')->name('devices.revoke');
});
