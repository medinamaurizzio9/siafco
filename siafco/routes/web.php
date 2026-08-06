<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliateAccessController;
use App\Http\Controllers\AffiliateAdministrationController;
use App\Http\Controllers\AffiliateBenefitController;
use App\Http\Controllers\AffiliatePanelController;
use App\Http\Controllers\AffiliateProfileController;
use App\Http\Controllers\AffiliatePasswordController;
use App\Http\Controllers\AffiliationPlanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\HomeRedirectController;
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
use App\Http\Controllers\Store\CartController as StoreCartController;
use App\Http\Controllers\Store\CatalogController as StoreCatalogController;
use App\Http\Controllers\Store\CheckoutController as StoreCheckoutController;
use App\Http\Controllers\Store\OrderController as StoreWebOrderController;
use App\Http\Controllers\Store\ReceiptController as StoreReceiptController;
use App\Http\Controllers\Store\WhatsAppController as StoreWhatsAppController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\Store\DashboardController as StoreDashboardController;
use App\Http\Controllers\Admin\Store\SettingController as StoreSettingController;
use App\Http\Controllers\Admin\Store\CategoryController as StoreCategoryController;
use App\Http\Controllers\Admin\Store\CouponController as StoreCouponController;
use App\Http\Controllers\Admin\Store\OrderController as StoreOrderController;
use App\Http\Controllers\Admin\Store\OrderReceiptController as StoreAdminOrderReceiptController;
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

Route::get('/', [HomeRedirectController::class, 'root']);

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
    Route::get('/cerrar-sesion/confirmar', [AuthController::class, 'confirmLogout'])->name('logout.confirm');
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

    Route::prefix('tienda')->name('store.')->middleware(['role:afiliado', 'affiliate.store.active'])->group(function () {
        Route::get('/', [StoreCatalogController::class, 'index'])->name('catalog.index');
        Route::get('/productos/{slug}', [StoreCatalogController::class, 'show'])->name('catalog.show');
        Route::get('/carrito', [StoreCartController::class, 'show'])->name('cart.show');
        Route::post('/carrito', [StoreCartController::class, 'store'])->name('cart.store');
        Route::patch('/carrito/{lineKey}', [StoreCartController::class, 'update'])->name('cart.update');
        Route::delete('/carrito/{lineKey}', [StoreCartController::class, 'destroy'])->name('cart.destroy');
        Route::delete('/carrito', [StoreCartController::class, 'clear'])->name('cart.clear');
        Route::get('/checkout', [StoreCheckoutController::class, 'show'])->name('checkout.show');
        Route::post('/pedidos', [StoreCheckoutController::class, 'store'])->middleware('throttle:6,1')->name('orders.store');
        Route::get('/mis-pedidos', [StoreWebOrderController::class, 'index'])->name('orders.index');
        Route::get('/pedidos/{order:code}', [StoreWebOrderController::class, 'show'])->name('orders.show');
        Route::post('/pedidos/{order:code}/comprobante', [StoreReceiptController::class, 'store'])->middleware('throttle:5,1')->name('orders.receipts.store');
        Route::get('/pedidos/{order:code}/comprobante/{receipt:public_id}', [StoreReceiptController::class, 'show'])->name('orders.receipts.show');
        Route::post('/pedidos/{order:code}/whatsapp', [StoreWhatsAppController::class, 'store'])->middleware('throttle:6,1')->name('orders.whatsapp');
    });

    Route::middleware('role:afiliado')->group(function () {
        Route::get('/afiliado/credencial', [CredentialController::class, 'affiliatePreview'])->name('affiliate.credential.preview');
        Route::get('/afiliado/credencial/pdf', [CredentialController::class, 'affiliatePdf'])->name('affiliate.credential.pdf');
        Route::get('/afiliado/credencial/png', [CredentialController::class, 'affiliatePng'])->name('affiliate.credential.png');
    });

    Route::get('/credenciales', PlaceholderController::class)
        ->defaults('title', 'Credenciales')
        ->defaults('message', 'Las credenciales se generan desde la ficha de cada afiliado activo.')
        ->middleware('permission:credentials.view')
        ->name('credentials.index');
    Route::get('/credenciales/{affiliate}', [CredentialController::class, 'preview'])
        ->middleware('permission:credentials.view')
        ->name('credenciales.show');
    Route::get('/credenciales/{affiliate}/pdf', [CredentialController::class, 'adminPdf'])->name('credenciales.pdf');
    Route::delete('/afiliados/{affiliate}', [AffiliateController::class, 'destroy'])
        ->middleware('permission:affiliates.soft_delete,affiliates.delete')
        ->name('affiliates.destroy');
    Route::post('/afiliados/{affiliate}/restaurar', [AffiliateAdministrationController::class, 'restore'])
        ->middleware('permission:affiliates.restore')
        ->name('affiliates.restore');

    Route::post('/pagos/{payment}/comprobante', [PaymentController::class, 'updateProof'])
        ->middleware('role:afiliado,secretaria,administrador,administrador_sector,cajero')
        ->name('payments.proof');

    Route::prefix('pagos')->name('payments.')->group(function () {
        Route::get('/crear', [PaymentController::class, 'create'])
            ->middleware('permission:payments.create')->name('create');
        Route::post('/', [PaymentController::class, 'store'])
            ->middleware('permission:payments.create')->name('store');
        Route::get('/{payment}', [PaymentController::class, 'show'])
            ->middleware('permission:payments.view')->name('show');
        Route::get('/{payment}/editar', [PaymentController::class, 'edit'])
            ->middleware('permission:payments.update_pending')->name('edit');
        Route::put('/{payment}', [PaymentController::class, 'update'])
            ->middleware('permission:payments.update_pending')->name('update');
        Route::post('/{payment}/confirmar', [PaymentController::class, 'confirm'])
            ->middleware('permission:payments.confirm')->name('confirm');
        Route::post('/{payment}/rechazar', [PaymentController::class, 'reject'])
            ->middleware('permission:payments.reject')->name('reject');
        Route::post('/{payment}/anular', [PaymentController::class, 'void'])
            ->middleware('permission:payments.void')->name('void');
        Route::get('/{payment}/comprobante-admin', [PaymentController::class, 'voucher'])
            ->middleware('permission:payments.view_receipt')->name('voucher');
        Route::get('/{payment}/recibo', [PaymentController::class, 'receipt'])
            ->middleware('permission:payments.view_receipt')->name('receipt');
        Route::get('/{payment}/recibo/descargar', [PaymentController::class, 'downloadReceipt'])
            ->middleware('permission:payments.download_receipt')->name('receipt.download');
    });

    Route::get('/dashboard', [HomeRedirectController::class, 'dashboard'])->name('admin.dashboard');

    Route::middleware('role:administrador,superadministrador,gerente,administrador_sector,secretaria,cajero,consulta')->group(function () {
        Route::get('/afiliados', [AffiliateController::class, 'index'])->name('affiliates.index');
        Route::get('/afiliados/{affiliate}', [AffiliateController::class, 'show'])->name('affiliates.show');
        Route::get('/pagos', [PaymentController::class, 'index'])->middleware('permission:payments.view')->name('payments.index');
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

    Route::middleware('role:administrador,superadministrador,gerente,secretaria,cajero,consulta')->group(function () {
        Route::patch('/admin/afiliados/{affiliate}/datos-personales', [AffiliateAdministrationController::class, 'updatePersonal'])
            ->middleware('permission:affiliates.update_personal')->name('admin.affiliates.personal.update');
        Route::patch('/admin/afiliados/{affiliate}/datos-institucionales', [AffiliateAdministrationController::class, 'updateInstitutional'])
            ->middleware('permission:affiliates.update_institutional')->name('admin.affiliates.institutional.update');
        Route::patch('/admin/afiliados/{affiliate}/sector', [AffiliateAdministrationController::class, 'changeSector'])
            ->middleware('permission:affiliates.change_sector')->name('admin.affiliates.sector.update');
        Route::patch('/admin/afiliados/{affiliate}/plan', [AffiliateAdministrationController::class, 'changePlan'])
            ->middleware('permission:affiliates.change_plan')->name('admin.affiliates.plan.update');
        Route::patch('/admin/afiliados/{affiliate}/estado', [AffiliateAdministrationController::class, 'changeStatus'])
            ->middleware('permission:affiliates.change_status')->name('admin.affiliates.status.update');
        Route::post('/admin/afiliados/{affiliate}/fotografia', [AffiliateAdministrationController::class, 'updatePhoto'])
            ->middleware('permission:affiliates.manage_photo')->name('admin.affiliates.photo.update');
        Route::post('/admin/afiliados/{affiliate}/credencial/regenerar', [AffiliateAdministrationController::class, 'regenerateCredential'])
            ->middleware('permission:affiliates.manage_credential')->name('admin.affiliates.credential.regenerate');
        Route::post('/admin/afiliados/{affiliate}/credencial/suspender', [AffiliateAdministrationController::class, 'suspendCredential'])
            ->middleware('permission:affiliates.manage_credential')->name('admin.affiliates.credential.suspend');
        Route::post('/admin/afiliados/{affiliate}/credencial/reactivar', [AffiliateAdministrationController::class, 'reactivateCredential'])
            ->middleware('permission:affiliates.manage_credential')->name('admin.affiliates.credential.reactivate');
    });

    Route::middleware('role:administrador,superadministrador,secretaria')->group(function () {
        Route::get('/qr-institucional', [InstitutionalQrController::class, 'show'])->name('institutional-qr.show');
        Route::post('/qr-institucional', [InstitutionalQrController::class, 'update'])->name('institutional-qr.update');
        Route::post('/admin/afiliados/{affiliate}/restablecer-contrasena', [AffiliatePasswordController::class, 'reset'])
            ->middleware('throttle:3,1')->name('admin.affiliates.password.reset');
        Route::post('/admin/afiliados/{affiliate}/bloquear-acceso', [AffiliateAccessController::class, 'block'])
            ->middleware('throttle:6,1')->name('admin.affiliates.access.block');
        Route::post('/admin/afiliados/{affiliate}/activar-acceso', [AffiliateAccessController::class, 'activate'])
            ->middleware('throttle:6,1')->name('admin.affiliates.access.activate');
        Route::post('/admin/afiliados/{affiliate}/cerrar-sesiones', [AffiliateAccessController::class, 'revokeSessions'])
            ->middleware('throttle:6,1')->name('admin.affiliates.access.revoke-sessions');
        Route::get('/admin/configuracion-institucional', [InstitutionalSettingController::class, 'edit'])->name('institutional-settings.edit');
        Route::put('/admin/configuracion-institucional', [InstitutionalSettingController::class, 'update'])->name('institutional-settings.update');
        Route::get('/admin/credenciales/{affiliate}/preview', [CredentialController::class, 'preview'])->name('credentials.preview');
        Route::get('/admin/credenciales/{affiliate}/pdf', [CredentialController::class, 'adminPdf'])->name('credentials.pdf');
        Route::get('/admin/credenciales/{affiliate}/png', [CredentialController::class, 'adminPng'])->name('credentials.png');
        Route::get('/admin/credenciales/{affiliate}/imprimir', [CredentialController::class, 'print'])->name('credentials.print');
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

    Route::prefix('administracion')->name('administration.')->group(function () {
        Route::get('/roles-permisos', [RolePermissionController::class, 'index'])
            ->middleware('permission:roles.view')->name('roles.index');
        Route::get('/roles-permisos/{role}', [RolePermissionController::class, 'edit'])
            ->middleware('permission:roles.view')->name('roles.edit');
        Route::patch('/roles-permisos/{role}', [RolePermissionController::class, 'update'])
            ->middleware('permission:roles.update')->name('roles.update');
        Route::post('/roles-permisos/{role}/restaurar', [RolePermissionController::class, 'reset'])
            ->middleware('permission:roles.update')->name('roles.reset');
        Route::get('/auditoria', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.view')->name('audit.index');
        Route::get('/auditoria/exportar', [AuditLogController::class, 'export'])
            ->middleware('permission:audit.export')->name('audit.export');
        Route::get('/auditoria/{audit}', [AuditLogController::class, 'show'])
            ->middleware('permission:audit.view')->name('audit.show');
    });

    Route::prefix('admin/mini-tienda')->name('admin.store.')->middleware('permission:store.view')->group(function () {
        Route::get('/', StoreDashboardController::class)->name('dashboard');
        Route::get('pedidos', [StoreOrderController::class, 'index'])->name('orders.index');
        Route::get('pedidos/{order:code}', [StoreOrderController::class, 'show'])->name('orders.show');
        Route::patch('pedidos/{order:code}/estado', [StoreOrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('pedidos/{order:code}/comprobantes/{receipt:public_id}', [StoreAdminOrderReceiptController::class, 'show'])->name('orders.receipts.show');
        Route::post('pedidos/{order:code}/comprobantes/{receipt:public_id}/confirmar', [StoreAdminOrderReceiptController::class, 'confirm'])->name('orders.receipts.confirm');
        Route::post('pedidos/{order:code}/comprobantes/{receipt:public_id}/rechazar', [StoreAdminOrderReceiptController::class, 'reject'])->name('orders.receipts.reject');
        Route::resource('categorias', StoreCategoryController::class)->except('show')->parameters(['categorias' => 'category'])->names('categories');
        Route::resource('productos', StoreProductController::class)->except('show')->parameters(['productos' => 'product'])->names('products');
        Route::resource('cupones', StoreCouponController::class)->except('show')->parameters(['cupones' => 'coupon'])->names('coupons');
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
