<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliateBenefitController;
use App\Http\Controllers\AffiliatePanelController;
use App\Http\Controllers\AffiliateProfileController;
use App\Http\Controllers\AffiliatePasswordController;
use App\Http\Controllers\AffiliationPlanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstitutionalQrController;
use App\Http\Controllers\InstitutionalSettingController;
use App\Http\Controllers\InternalUserController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PublicAffiliationAdminController;
use App\Http\Controllers\PublicAffiliationController;
use App\Http\Controllers\PublicAffiliationQrController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\Admin\Store\DashboardController as StoreDashboardController;
use App\Http\Controllers\Admin\Store\SettingController as StoreSettingController;
use App\Http\Controllers\Admin\Store\CategoryController as StoreCategoryController;
use App\Http\Controllers\Admin\Store\ProductController as StoreProductController;
use App\Http\Controllers\Admin\Store\ProductVariantController as StoreProductVariantController;
use App\Http\Controllers\Admin\Store\ProductImageController as StoreProductImageController;
use App\Http\Controllers\Admin\Store\ShippingRateController as StoreShippingRateController;
use App\Http\Controllers\Investments\DashboardController as InvestmentDashboardController;
use App\Http\Controllers\Investments\InvestmentLotController;
use App\Http\Controllers\Investments\InvestorController;
use App\Http\Controllers\Investments\InvestorPanelController;
use App\Http\Controllers\Investments\InvestorTypeController;
use App\Http\Controllers\Investments\ReceiptController as InvestmentReceiptController;
use App\Http\Controllers\Investments\ReportController as InvestmentReportController;
use App\Http\Controllers\Investments\ReturnPeriodController;
use App\Http\Controllers\Investments\SettingController as InvestmentSettingController;
use App\Http\Controllers\Investments\ShareReservationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::get('/verificar/{token}', [VerificationController::class, 'show'])->name('verify.show');

Route::middleware('throttle:30,1')->prefix('afiliacion')->name('public-affiliation.')->group(function () {
    Route::get('/', [PublicAffiliationController::class, 'index'])->name('index');
    Route::get('/registro', [PublicAffiliationController::class, 'create'])->name('create');
    Route::post('/registro', [PublicAffiliationController::class, 'store'])->middleware('throttle:5,1')->name('store');
    Route::get('/{application}/pago', [PublicAffiliationController::class, 'payment'])->name('payment');
    Route::post('/{application}/pago', [PublicAffiliationController::class, 'storePayment'])->middleware('throttle:5,1')->name('payment.store');
    Route::get('/{application}/estado', [PublicAffiliationController::class, 'status'])->name('status');
    Route::get('/{application}/completado', [PublicAffiliationController::class, 'completed'])->name('completed');
});

Route::middleware(['auth', 'password.changed', 'affiliate.active-access'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/cambiar-contrasena-obligatoria', [PasswordController::class, 'forceEdit'])->name('password.force.edit');
    Route::patch('/cambiar-contrasena-obligatoria', [PasswordController::class, 'forceUpdate'])
        ->middleware('throttle:5,1')->name('password.force.update');
    Route::middleware('role:afiliado')->group(function () {
        Route::get('/mi-perfil', [AffiliateProfileController::class, 'show'])->name('affiliate.profile.show');
        Route::patch('/mi-perfil', [AffiliateProfileController::class, 'update'])->name('affiliate.profile.update');
        Route::patch('/mi-perfil/contrasena', [PasswordController::class, 'updateOwn'])
            ->middleware('throttle:5,1')->name('affiliate.profile.password.update');
        Route::get('/mi-perfil/pagos/{payment}/comprobante', [AffiliateProfileController::class, 'showPaymentReceipt'])
            ->name('affiliate.profile.payments.receipt');
    });

    Route::get('/panel-afiliado', [AffiliatePanelController::class, 'index'])
        ->middleware('role:afiliado')
        ->name('affiliate.panel');

    Route::middleware('role:afiliado')->group(function () {
        Route::get('/afiliado/credencial', [CredentialController::class, 'affiliatePreview'])->name('affiliate.credential.preview');
        Route::get('/afiliado/credencial/pdf', [CredentialController::class, 'affiliatePdf'])->name('affiliate.credential.pdf');
        Route::get('/afiliado/credencial/png', [CredentialController::class, 'affiliatePng'])->name('affiliate.credential.png');
    });

    Route::get('/credenciales', PlaceholderController::class)->defaults('title', 'Credenciales')->defaults('message', 'Las credenciales se generan desde la ficha de cada afiliado activo.')->middleware('role:administrador,administrador_sector,secretaria')->name('credentials.index');
    Route::get('/credenciales/{affiliate}', [CredentialController::class, 'preview'])->name('credenciales.show');
    Route::get('/credenciales/{affiliate}/pdf', [CredentialController::class, 'adminPdf'])->name('credenciales.pdf');
    Route::delete('/afiliados/{affiliate}', [AffiliateController::class, 'destroy'])
        ->middleware('role:administrador,superadministrador')
        ->name('affiliates.destroy');

    Route::post('/pagos/{payment}/comprobante', [PaymentController::class, 'updateProof'])
        ->middleware('role:afiliado,secretaria,administrador,administrador_sector,cajero')
        ->name('payments.proof');

    Route::middleware('role:administrador,superadministrador,administrador_sector,secretaria,cajero,consulta')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/afiliados', [AffiliateController::class, 'index'])->name('affiliates.index');
        Route::get('/afiliados/{affiliate}', [AffiliateController::class, 'show'])->name('affiliates.show');
        Route::get('/pagos', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reportes/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    });

    Route::middleware('role:administrador,administrador_sector,secretaria')->group(function () {
        Route::get('/afiliacion/solicitudes-publicas', [PublicAffiliationAdminController::class, 'index'])->name('public-affiliation.admin.index');
        Route::get('/afiliacion/qr-publico', [PublicAffiliationQrController::class, 'show'])->name('public-affiliation.qr.show');
        Route::get('/afiliacion/qr-publico/png', [PublicAffiliationQrController::class, 'png'])->name('public-affiliation.qr.png');
        Route::get('/afiliacion/qr-publico/pdf', [PublicAffiliationQrController::class, 'pdf'])->name('public-affiliation.qr.pdf');
        Route::get('/afiliacion/solicitudes-publicas/{application}', [PublicAffiliationAdminController::class, 'show'])->name('public-affiliation.admin.show');
        Route::post('/afiliacion/solicitudes-publicas/{application}/tomar', [PublicAffiliationAdminController::class, 'take'])->name('public-affiliation.admin.take');
        Route::post('/afiliacion/pagos/{payment}/confirmar', [PublicAffiliationAdminController::class, 'approve'])->name('public-affiliation.admin.approve');
        Route::post('/afiliacion/pagos/{payment}/rechazar', [PublicAffiliationAdminController::class, 'reject'])->name('public-affiliation.admin.reject');
        Route::get('/afiliacion/pagos/{payment}/comprobante', [PublicAffiliationAdminController::class, 'receipt'])->name('public-affiliation.admin.receipt');
        Route::resource('sectores', SectorController::class)->except('show')->parameters(['sectores' => 'sector'])->names('sectors');
        Route::resource('planes', AffiliationPlanController::class)->except('show')->parameters(['planes' => 'plan'])->names('plans');
        Route::resource('beneficios-afiliado', AffiliateBenefitController::class)
            ->except('show')->parameters(['beneficios-afiliado' => 'affiliateBenefit'])->names('affiliate-benefits');
        Route::get('/afiliacion/configuracion', PlaceholderController::class)->defaults('title', 'Configuracion de afiliacion')->defaults('message', 'Planes, QR bancario, reglas de activacion, diseno de credencial y textos institucionales del modulo de afiliados.')->name('affiliation.settings.edit');
        Route::get('/afiliados/crear/nuevo', [AffiliateController::class, 'create'])->name('affiliates.create');
        Route::post('/afiliados', [AffiliateController::class, 'store'])->name('affiliates.store');
        Route::get('/afiliados/{affiliate}/editar', [AffiliateController::class, 'edit'])->name('affiliates.edit');
        Route::put('/afiliados/{affiliate}', [AffiliateController::class, 'update'])->name('affiliates.update');
    });

    Route::middleware('role:administrador,superadministrador,secretaria')->group(function () {
        Route::get('/qr-institucional', [InstitutionalQrController::class, 'show'])->name('institutional-qr.show');
        Route::post('/qr-institucional', [InstitutionalQrController::class, 'update'])->name('institutional-qr.update');
        Route::post('/admin/afiliados/{affiliate}/restablecer-contrasena', [AffiliatePasswordController::class, 'reset'])
            ->middleware('throttle:3,1')->name('admin.affiliates.password.reset');
        Route::get('/admin/configuracion-institucional', [InstitutionalSettingController::class, 'edit'])->name('institutional-settings.edit');
        Route::put('/admin/configuracion-institucional', [InstitutionalSettingController::class, 'update'])->name('institutional-settings.update');
        Route::get('/admin/credenciales/{affiliate}/preview', [CredentialController::class, 'preview'])->name('credentials.preview');
        Route::get('/admin/credenciales/{affiliate}/pdf', [CredentialController::class, 'adminPdf'])->name('credentials.pdf');
        Route::get('/admin/credenciales/{affiliate}/png', [CredentialController::class, 'adminPng'])->name('credentials.png');
        Route::get('/admin/credenciales/{affiliate}/imprimir', [CredentialController::class, 'print'])->name('credentials.print');
    });

    Route::middleware('role:administrador,cajero')->group(function () {
        Route::post('/pagos/{payment}/confirmar', [PaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('/pagos/{payment}/rechazar', [PaymentController::class, 'reject'])->name('payments.reject');
    });

    Route::get('/panel-accionista', [InvestorPanelController::class, 'index'])
        ->middleware('role:accionista,afiliado,administrador,caja,cajero,contabilidad')
        ->name('investments.panel');

    Route::prefix('inversiones')->name('investments.')->middleware('role:administrador,caja,cajero,contabilidad')->group(function () {
        Route::get('/dashboard', [InvestmentDashboardController::class, 'index'])->name('dashboard');
        Route::resource('accionistas', InvestorController::class)->except('destroy')->parameters(['accionistas' => 'investor'])->names('investors');
        Route::resource('tipos-inversionista', InvestorTypeController::class)->except('show', 'destroy')->parameters(['tipos-inversionista' => 'investorType'])->names('investor-types');

        Route::get('reservas', [ShareReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservas/crear', [ShareReservationController::class, 'create'])->name('reservations.create');
        Route::post('reservas', [ShareReservationController::class, 'store'])->name('reservations.store');
        Route::get('reservas/{reservation}', [ShareReservationController::class, 'show'])->name('reservations.show');
        Route::post('reservas/{reservation}/convertir', [ShareReservationController::class, 'convert'])->name('reservations.convert');
        Route::post('reservas/{reservation}/cerrar', [ShareReservationController::class, 'close'])->name('reservations.close');

        Route::get('lotes', [InvestmentLotController::class, 'index'])->name('lots.index');
        Route::get('lotes/crear', [InvestmentLotController::class, 'create'])->name('lots.create');
        Route::post('lotes', [InvestmentLotController::class, 'store'])->name('lots.store');
        Route::get('lotes/{lot}', [InvestmentLotController::class, 'show'])->name('lots.show');
        Route::post('lotes/{lot}/aprobar', [InvestmentLotController::class, 'approve'])->name('lots.approve');

        Route::get('rendimientos', [ReturnPeriodController::class, 'index'])->name('returns.index');
        Route::get('rendimientos/{period}', [ReturnPeriodController::class, 'show'])->name('returns.show');
        Route::post('rendimientos/{period}/preparar', [ReturnPeriodController::class, 'prepare'])->name('returns.prepare');
        Route::post('rendimientos/{period}/aprobar', [ReturnPeriodController::class, 'approve'])->name('returns.approve');
        Route::post('rendimientos/{period}/rechazar', [ReturnPeriodController::class, 'reject'])->name('returns.reject');
        Route::post('rendimientos/{period}/recibo', [InvestmentReceiptController::class, 'issue'])->name('receipts.issue');

        Route::get('recibos', [InvestmentReceiptController::class, 'index'])->name('receipts.index');
        Route::get('recibos/{receipt}', [InvestmentReceiptController::class, 'show'])->name('receipts.show');
        Route::get('recibos/{receipt}/pdf', [InvestmentReceiptController::class, 'pdf'])->name('receipts.pdf');
        Route::post('recibos/{receipt}/anular', [InvestmentReceiptController::class, 'void'])->name('receipts.void');

        Route::get('aprobaciones', [ReturnPeriodController::class, 'index'])->defaults('status', 'pending_approval')->name('approvals.index');
        Route::get('configuracion', [InvestmentSettingController::class, 'edit'])->name('settings.edit');
        Route::put('configuracion', [InvestmentSettingController::class, 'update'])->name('settings.update');
        Route::get('reportes', [InvestmentReportController::class, 'index'])->name('reports.index');
        Route::get('reportes/pdf', [InvestmentReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('reportes/csv', [InvestmentReportController::class, 'csv'])->name('reports.csv');
    });

    Route::view('/creditos', 'credits.placeholder')
        ->middleware('role:administrador,administrador_sector,secretaria,cajero,consulta')
        ->name('credits.placeholder');

    Route::prefix('creditos')->name('credits.')->middleware('role:administrador,administrador_sector,secretaria,cajero,caja,contabilidad,consulta')->group(function () {
        Route::get('/productos', PlaceholderController::class)->defaults('title', 'Productos de credito')->defaults('message', 'Tipos de credito, tasas y condiciones se construiran en la fase de creditos.')->name('products.index');
        Route::get('/solicitudes', PlaceholderController::class)->defaults('title', 'Solicitudes de credito')->defaults('message', 'Registro y revision de solicitudes de credito pendiente de implementacion.')->name('applications.index');
        Route::get('/simulador', PlaceholderController::class)->defaults('title', 'Simulador de creditos')->defaults('message', 'Simulador preparado para calcular cuotas, plazos e intereses.')->name('simulator');
        Route::get('/aprobados', PlaceholderController::class)->defaults('title', 'Creditos aprobados')->defaults('message', 'Gestion de creditos aprobados pendiente para la fase financiera.')->name('approved.index');
        Route::get('/cuotas', PlaceholderController::class)->defaults('title', 'Cuotas de credito')->defaults('message', 'Calendario de cuotas y seguimiento se implementara en el modulo de creditos.')->name('installments.index');
        Route::get('/pagos', PlaceholderController::class)->defaults('title', 'Pagos de credito')->defaults('message', 'Registro de pagos de credito pendiente de implementacion.')->name('payments.index');
        Route::get('/mora', PlaceholderController::class)->defaults('title', 'Mora por atraso')->defaults('message', 'Calculo de mora y alertas se agregaran en la fase de creditos.')->name('late-fees.index');
        Route::get('/reportes', PlaceholderController::class)->defaults('title', 'Reportes de creditos')->defaults('message', 'Reportes financieros de creditos pendientes de implementacion.')->name('reports.index');
        Route::get('/configuracion', PlaceholderController::class)->defaults('title', 'Configuracion de creditos')->defaults('message', 'Tasas, plazos, mora, productos, documentos y reglas de aprobacion se configuraran aqui.')->name('settings.edit');
    });

    Route::prefix('administracion/usuarios')->name('admin.users.')->controller(InternalUserController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/crear', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/{user}/restaurar', 'restore')->name('restore');
        Route::get('/{user}', 'show')->name('show');
        Route::get('/{user}/editar', 'edit')->name('edit');
        Route::patch('/{user}', 'update')->name('update');
        Route::post('/{user}/bloquear', 'block')->name('block');
        Route::post('/{user}/activar', 'activate')->name('activate');
        Route::post('/{user}/restablecer-contrasena', 'resetPassword')
            ->middleware('throttle:3,1')->name('password.reset');
        Route::delete('/{user}', 'destroy')->name('destroy');
    });

    Route::prefix('administracion')->name('administration.')->middleware('role:administrador')->group(function () {
        Route::get('/roles-permisos', PlaceholderController::class)->defaults('title', 'Roles y permisos')->defaults('message', 'Gestion detallada de permisos por modulo pendiente de implementacion.')->name('roles.index');
        Route::get('/auditoria', PlaceholderController::class)->defaults('title', 'Auditoria')->defaults('message', 'Consulta avanzada de auditoria preparada para una fase posterior.')->name('audit.index');
    });

    Route::prefix('admin/mini-tienda')->name('admin.store.')->middleware('role:superadministrador,administrador,gerente,secretaria,cajero,consulta')->group(function () {
        Route::get('/', StoreDashboardController::class)->name('dashboard');
        Route::resource('categorias', StoreCategoryController::class)->except('show')->parameters(['categorias' => 'category'])->names('categories');
        Route::resource('productos', StoreProductController::class)->except('show')->parameters(['productos' => 'product'])->names('products');
        Route::prefix('productos/{product}')->name('products.')->group(function () {
            Route::resource('variantes', StoreProductVariantController::class)->except('index', 'show')->parameters(['variantes' => 'variant'])->names('variants');
            Route::post('imagenes', [StoreProductImageController::class, 'store'])->name('images.store');
            Route::patch('imagenes/{image}', [StoreProductImageController::class, 'update'])->name('images.update');
            Route::post('imagenes/{image}/principal', [StoreProductImageController::class, 'makePrimary'])->name('images.primary');
            Route::delete('imagenes/{image}', [StoreProductImageController::class, 'destroy'])->name('images.destroy');
        });
        Route::resource('tarifas-envio', StoreShippingRateController::class)->except('show')->parameters(['tarifas-envio' => 'shippingRate'])->names('shipping-rates');
        Route::get('/configuracion', [StoreSettingController::class, 'edit'])->name('settings.edit');
        Route::put('/configuracion', [StoreSettingController::class, 'update'])->name('settings.update');
    });

    Route::prefix('configuracion-general')->name('settings.')->middleware('role:administrador,secretaria')->group(function () {
        Route::get('/seguridad', PlaceholderController::class)->defaults('title', 'Seguridad')->defaults('message', 'Opciones de seguridad del sistema preparadas para una fase posterior.')->name('security');
        Route::get('/sistema', PlaceholderController::class)->defaults('title', 'Configuracion del sistema')->defaults('message', 'Zona horaria, moneda por defecto y opciones generales del sistema se ampliaran aqui.')->name('system');
    });
});
