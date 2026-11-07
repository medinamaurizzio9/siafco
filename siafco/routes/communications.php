<?php

use App\Http\Controllers\CommunicationAdminController as Admin;
use App\Http\Controllers\CommunicationInboxController as Inbox;
use App\Models\Communication;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed', 'affiliate.profile.complete', 'affiliate.active-access', 'throttle:120,1'])->group(function () {
    Route::prefix('comunicaciones/comunicados')->name('communications.')->controller(Admin::class)->middleware('can:manage,'.Communication::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/crear', 'edit')->name('create');
        Route::post('/destinatarios/total', 'audienceCount')->name('audience-count');
        Route::get('/afiliados', 'candidates')->name('candidates');
        Route::post('/', 'save')->name('store');
        Route::get('/{item}', 'preview')->name('preview');
        Route::get('/{item}/editar', 'edit')->name('edit');
        Route::put('/{item}', 'save')->name('update');
        Route::post('/{item}/publicar', 'publish')->middleware('can:publish,item')->name('publish');
        Route::post('/{item}/archivar', 'archive')->middleware('can:publish,item')->name('archive');
        Route::get('/{item}/reporte', 'report')->middleware('can:report,item')->name('report');
        Route::get('/{item}/adjuntos/{file}', 'file')->whereNumber('file')->name('file');
    });
    Route::prefix('mis-comunicados')->name('inbox.')->controller(Inbox::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'dismiss')->name('dismiss');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::post('/{id}/lectura', 'read')->whereNumber('id')->name('read');
        Route::get('/{id}/adjuntos/{file}', 'file')->whereNumber(['id', 'file'])->name('file');
    });
});
