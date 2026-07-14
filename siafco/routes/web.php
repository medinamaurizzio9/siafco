<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliatePanelController;
use App\Http\Controllers\AffiliationPlanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstitutionalQrController;
use App\Http\Controllers\InstitutionalSettingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::get('/verificar/{token}', [VerificationController::class, 'show'])->name('verify.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/panel-afiliado', [AffiliatePanelController::class, 'index'])
        ->middleware('role:afiliado')
        ->name('affiliate.panel');

    Route::middleware('role:afiliado')->group(function () {
        Route::get('/afiliado/credencial', [CredentialController::class, 'affiliatePreview'])->name('affiliate.credential.preview');
        Route::get('/afiliado/credencial/pdf', [CredentialController::class, 'affiliatePdf'])->name('affiliate.credential.pdf');
        Route::get('/afiliado/credencial/png', [CredentialController::class, 'affiliatePng'])->name('affiliate.credential.png');
    });

    Route::get('/credenciales/{affiliate}', [CredentialController::class, 'preview'])->name('credenciales.show');
    Route::get('/credenciales/{affiliate}/pdf', [CredentialController::class, 'adminPdf'])->name('credenciales.pdf');

    Route::post('/pagos/{payment}/comprobante', [PaymentController::class, 'updateProof'])
        ->middleware('role:afiliado,secretaria,administrador,administrador_sector,cajero')
        ->name('payments.proof');

    Route::middleware('role:administrador,administrador_sector,secretaria,cajero,consulta')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/afiliados', [AffiliateController::class, 'index'])->name('affiliates.index');
        Route::get('/afiliados/{affiliate}', [AffiliateController::class, 'show'])->name('affiliates.show');
        Route::get('/pagos', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reportes/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    });

    Route::middleware('role:administrador,administrador_sector,secretaria')->group(function () {
        Route::resource('sectores', SectorController::class)->except('show')->parameters(['sectores' => 'sector'])->names('sectors');
        Route::resource('planes', AffiliationPlanController::class)->except('show')->parameters(['planes' => 'plan'])->names('plans');
        Route::get('/afiliados/crear/nuevo', [AffiliateController::class, 'create'])->name('affiliates.create');
        Route::post('/afiliados', [AffiliateController::class, 'store'])->name('affiliates.store');
        Route::get('/afiliados/{affiliate}/editar', [AffiliateController::class, 'edit'])->name('affiliates.edit');
        Route::put('/afiliados/{affiliate}', [AffiliateController::class, 'update'])->name('affiliates.update');
        Route::get('/qr-institucional', [InstitutionalQrController::class, 'show'])->name('institutional-qr.show');
        Route::post('/qr-institucional', [InstitutionalQrController::class, 'update'])->name('institutional-qr.update');
    });

    Route::middleware('role:administrador,secretaria')->group(function () {
        Route::get('/admin/configuracion-institucional', [InstitutionalSettingController::class, 'edit'])->name('institutional-settings.edit');
        Route::put('/admin/configuracion-institucional', [InstitutionalSettingController::class, 'update'])->name('institutional-settings.update');
        Route::get('/admin/credenciales/{affiliate}/preview', [CredentialController::class, 'preview'])->name('credentials.preview');
        Route::get('/admin/credenciales/{affiliate}/pdf', [CredentialController::class, 'adminPdf'])->name('credentials.pdf');
        Route::get('/admin/credenciales/{affiliate}/png', [CredentialController::class, 'adminPng'])->name('credentials.png');
    });

    Route::middleware('role:administrador,cajero')->group(function () {
        Route::post('/pagos/{payment}/confirmar', [PaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('/pagos/{payment}/rechazar', [PaymentController::class, 'reject'])->name('payments.reject');
    });

    Route::view('/creditos', 'credits.placeholder')
        ->middleware('role:administrador,administrador_sector,secretaria,cajero,consulta')
        ->name('credits.placeholder');
});
