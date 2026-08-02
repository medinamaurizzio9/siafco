<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SIAFCO' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if(!empty($credentialAssets))
        @vite('resources/css/credential.css')
    @endif
    @stack('styles')
</head>
<body class="bg-slate-100 text-slate-950">
<div class="min-h-screen lg:flex" data-sidebar-shell>
    @auth
        @php
            $user = auth()->user();
            $canManageAffiliation = $user->hasRole(['administrador', 'administrador_sector', 'secretaria']);
            $canManagePaymentQr = $user->hasRole(['administrador', 'superadministrador', 'secretaria']);
            $canViewAffiliation = $user->hasRole(['administrador', 'superadministrador', 'administrador_sector', 'secretaria', 'cajero', 'consulta']);
            $canManageInvestments = $user->hasRole(['administrador', 'caja', 'cajero', 'contabilidad']);
            $canViewCredits = $user->hasRole(['administrador', 'administrador_sector', 'secretaria', 'cajero', 'caja', 'contabilidad', 'consulta']);
            $canManageUsers = $user->isInternal() && $user->hasPermission('users.view');
            $canAdmin = $user->hasRole('administrador') || $canManageUsers;
            $canGeneralSettings = $user->hasRole(['administrador', 'secretaria']);
            $canViewStore = $user->isInternal() && $user->hasPermission('store.view');
            $canManageStoreProducts = $user->isInternal() && $user->hasPermission('store.manage-products');
            $canManageStoreSettings = $user->isInternal() && $user->hasPermission('store.manage-settings');
            $canManageStoreShipping = $user->isInternal() && $user->hasPermission('store.manage-shipping');
            $canManageStoreCoupons = $user->isInternal() && $user->hasPermission('store.manage-coupons');
            $canManageStoreOrders = $user->isInternal() && $user->hasPermission('store.manage-orders');
            $isPersonalOnly = $user->hasRole(['afiliado', 'accionista']) && ! $canViewAffiliation && ! $canManageInvestments;

            $openModule = match (true) {
                request()->routeIs('admin.dashboard') => 'home',
                request()->routeIs('affiliates.*', 'affiliate-benefits.*', 'sectors.*', 'plans.*', 'payments.*', 'credentials.*', 'credenciales.*', 'institutional-qr.*', 'reports.*', 'affiliation.*', 'public-affiliation.admin.*') => 'affiliation',
                request()->routeIs('investments.*') && ! request()->routeIs('investments.panel') => 'investments',
                request()->routeIs('credits.*') => 'credits',
                request()->routeIs('admin.store.*') => 'store',
                request()->routeIs('administration.*', 'admin.users.*') => 'administration',
                request()->routeIs('institutional-settings.*', 'settings.*') => 'general-settings',
                request()->routeIs('affiliate.*', 'investments.panel') => 'personal',
                default => 'home',
            };

            $navLink = function (string $route, string $label, array $params = [], array|string $active = []) {
                $activePatterns = $active ?: [$route];
                $isActive = request()->routeIs(...(array) $activePatterns);

                return '<a class="'.($isActive ? 'nav-link nav-link-active' : 'nav-link').'" href="'.route($route, $params).'" data-sidebar-link>'.$label.'</a>';
            };

            $soon = fn (string $route, string $label) => $navLink($route, $label);
        @endphp

        <aside class="fixed inset-y-0 left-0 z-40 hidden w-80 overflow-y-auto bg-[#0b1f3a] text-white shadow-xl lg:static lg:block lg:w-72 lg:shadow-none" data-sidebar>
            <div class="flex items-center justify-between px-5 py-4">
                <a href="{{ auth()->user()->role === 'afiliado' ? route('affiliate.panel') : (auth()->user()->role === 'accionista' ? route('investments.panel') : route('admin.dashboard')) }}" class="flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center overflow-hidden rounded border border-[#d4af37] bg-white text-lg font-black text-[#0b1f3a]">
                        @if($institution->logoUrl())
                            <img class="h-full w-full object-contain p-1" src="{{ $institution->logoUrl() }}" alt="Logo">
                        @else
                            S
                        @endif
                    </span>
                    <span>
                        <span class="block text-lg font-black text-[#d4af37]">SIAFCO</span>
                        <span class="block text-xs text-slate-300">{{ $institution->institution_name }}</span>
                    </span>
                </a>
                <button type="button" class="rounded px-2 py-1 text-sm font-black text-[#d4af37] lg:hidden" data-sidebar-close>✕</button>
            </div>

            <nav class="grid gap-2 px-3 pb-4 text-sm" data-sidebar-accordion data-current-module="{{ $openModule }}">
                @unless($isPersonalOnly)
                    <section class="nav-module" data-accordion-module="home">
                        <button type="button" class="nav-module-button" data-accordion-toggle aria-expanded="{{ $openModule === 'home' ? 'true' : 'false' }}">
                            <span>Inicio</span><span class="nav-chevron">⌄</span>
                        </button>
                        <div class="nav-module-panel {{ $openModule === 'home' ? '' : 'hidden' }}">
                            {!! $navLink('admin.dashboard', 'Dashboard general', [], ['admin.dashboard']) !!}
                        </div>
                    </section>

                    @if($canViewAffiliation)
                        <section class="nav-module" data-accordion-module="affiliation">
                            <button type="button" class="nav-module-button" data-accordion-toggle aria-expanded="{{ $openModule === 'affiliation' ? 'true' : 'false' }}">
                                <span>Afiliacion</span><span class="nav-chevron">⌄</span>
                            </button>
                            <div class="nav-module-panel {{ $openModule === 'affiliation' ? '' : 'hidden' }}">
                                {!! $navLink('admin.dashboard', 'Dashboard', [], ['admin.dashboard']) !!}
                                {!! $navLink('affiliates.index', 'Afiliados', [], ['affiliates.*']) !!}
                                @if($canManageAffiliation)
                                    {!! $navLink('public-affiliation.admin.index', 'Solicitudes publicas', [], ['public-affiliation.admin.*']) !!}
                                    {!! $navLink('sectors.index', 'Sectores', [], ['sectors.*']) !!}
                                    {!! $navLink('plans.index', 'Planes de afiliacion', [], ['plans.*']) !!}
                                    {!! $navLink('affiliate-benefits.index', 'Servicios y beneficios', [], ['affiliate-benefits.*']) !!}
                                @endif
                                {!! $navLink('payments.index', 'Pagos de afiliacion', [], ['payments.*']) !!}
                                @if($canManageAffiliation)
                                    {!! $navLink('credentials.index', 'Credenciales', [], ['credentials.*', 'credenciales.*']) !!}
                                    {!! $navLink('public-affiliation.qr.show', 'QR publico de afiliacion', [], ['public-affiliation.qr.*']) !!}
                                @endif
                                @if($canManagePaymentQr)
                                    {!! $navLink('institutional-qr.show', 'QR y pago institucional', [], ['institutional-qr.*']) !!}
                                @endif
                                {!! $navLink('reports.index', 'Reportes de afiliacion', [], ['reports.*']) !!}
                                @if($canManageAffiliation)
                                    {!! $navLink('affiliation.settings.edit', 'Configuracion de afiliacion', [], ['affiliation.*']) !!}
                                @endif
                            </div>
                        </section>
                    @endif

                    @if($canManageInvestments)
                        <section class="nav-module" data-accordion-module="investments">
                            <button type="button" class="nav-module-button" data-accordion-toggle aria-expanded="{{ $openModule === 'investments' ? 'true' : 'false' }}">
                                <span>Accionistas e inversiones</span><span class="nav-chevron">⌄</span>
                            </button>
                            <div class="nav-module-panel {{ $openModule === 'investments' ? '' : 'hidden' }}">
                                {!! $navLink('investments.dashboard', 'Dashboard de inversiones', [], ['investments.dashboard']) !!}
                                {!! $navLink('investments.investors.index', 'Accionistas', [], ['investments.investors.*']) !!}
                                {!! $navLink('investments.investor-types.index', 'Tipos de inversionista', [], ['investments.investor-types.*']) !!}
                                {!! $navLink('investments.reservations.index', 'Reservas', [], ['investments.reservations.*']) !!}
                                {!! $navLink('investments.lots.create', 'Venta de acciones', [], ['investments.lots.create']) !!}
                                {!! $navLink('investments.lots.index', 'Lotes de inversion', [], ['investments.lots.index', 'investments.lots.show']) !!}
                                {!! $navLink('investments.returns.index', 'Rendimientos mensuales', [], ['investments.returns.*']) !!}
                                {!! $navLink('investments.returns.index', 'Bonos de produccion minera', ['bonus' => 1], ['investments.returns.*']) !!}
                                {!! $navLink('investments.receipts.index', 'Recibos', [], ['investments.receipts.*']) !!}
                                {!! $navLink('investments.approvals.index', 'Aprobaciones', [], ['investments.approvals.*']) !!}
                                {!! $navLink('investments.reports.index', 'Reportes de inversiones', [], ['investments.reports.*']) !!}
                                {!! $navLink('investments.settings.edit', 'Configuracion de inversiones', [], ['investments.settings.*']) !!}
                            </div>
                        </section>
                    @endif

                    @if($canViewCredits)
                        <section class="nav-module" data-accordion-module="credits">
                            <button type="button" class="nav-module-button" data-accordion-toggle aria-expanded="{{ $openModule === 'credits' ? 'true' : 'false' }}">
                                <span>Creditos</span><span class="nav-chevron">⌄</span>
                            </button>
                            <div class="nav-module-panel {{ $openModule === 'credits' ? '' : 'hidden' }}">
                                {!! $soon('credits.products.index', 'Productos o tipos de credito') !!}
                                {!! $soon('credits.applications.index', 'Solicitudes') !!}
                                {!! $soon('credits.simulator', 'Simulador') !!}
                                {!! $soon('credits.approved.index', 'Creditos aprobados') !!}
                                {!! $soon('credits.installments.index', 'Cuotas') !!}
                                {!! $soon('credits.payments.index', 'Pagos') !!}
                                {!! $soon('credits.late-fees.index', 'Mora') !!}
                                {!! $soon('credits.reports.index', 'Reportes de creditos') !!}
                                {!! $soon('credits.settings.edit', 'Configuracion de creditos') !!}
                            </div>
                        </section>
                    @endif

                    @if($canViewStore)
                        <section class="nav-module" data-accordion-module="store">
                            <button type="button" class="nav-module-button" data-accordion-toggle aria-expanded="{{ $openModule === 'store' ? 'true' : 'false' }}">
                                <span>Mini tienda</span><span class="nav-chevron">⌄</span>
                            </button>
                            <div class="nav-module-panel {{ $openModule === 'store' ? '' : 'hidden' }}">
                                {!! $navLink('admin.store.dashboard', 'Resumen', [], ['admin.store.dashboard']) !!}
                                {!! $navLink('admin.store.orders.index', 'Pedidos', [], ['admin.store.orders.*']) !!}
                                @if($canManageStoreProducts)
                                    {!! $navLink('admin.store.products.index', 'Productos', [], ['admin.store.products.*']) !!}
                                    {!! $navLink('admin.store.categories.index', 'Categorías', [], ['admin.store.categories.*']) !!}
                                @endif
                                @if($canManageStoreCoupons)
                                    {!! $navLink('admin.store.coupons.index', 'Cupones', [], ['admin.store.coupons.*']) !!}
                                @endif
                                @if($canManageStoreShipping)
                                    {!! $navLink('admin.store.shipping-rates.index', 'Tarifas de envío', [], ['admin.store.shipping-rates.*']) !!}
                                @endif
                                @if($canManageStoreSettings)
                                    {!! $navLink('admin.store.settings.edit', 'Configuración', [], ['admin.store.settings.*']) !!}
                                @endif
                            </div>
                        </section>
                    @endif

                    @if($canAdmin)
                        <section class="nav-module" data-accordion-module="administration">
                            <button type="button" class="nav-module-button" data-accordion-toggle aria-expanded="{{ $openModule === 'administration' ? 'true' : 'false' }}">
                                <span>Administracion</span><span class="nav-chevron">⌄</span>
                            </button>
                            <div class="nav-module-panel {{ $openModule === 'administration' ? '' : 'hidden' }}">
                                @if($canManageUsers)
                                    {!! $navLink('admin.users.index', 'Usuarios', [], ['admin.users.*']) !!}
                                @endif
                                @if($user->hasRole('administrador'))
                                    {!! $soon('administration.roles.index', 'Roles y permisos') !!}
                                    {!! $soon('administration.audit.index', 'Auditoria') !!}
                                @endif
                            </div>
                        </section>
                    @endif

                    @if($canGeneralSettings)
                        <section class="nav-module" data-accordion-module="general-settings">
                            <button type="button" class="nav-module-button" data-accordion-toggle aria-expanded="{{ $openModule === 'general-settings' ? 'true' : 'false' }}">
                                <span>Configuracion general</span><span class="nav-chevron">⌄</span>
                            </button>
                            <div class="nav-module-panel {{ $openModule === 'general-settings' ? '' : 'hidden' }}">
                                {!! $navLink('institutional-settings.edit', 'Datos generales de SIAFCO', [], ['institutional-settings.*']) !!}
                                {!! $soon('settings.security', 'Seguridad') !!}
                                {!! $soon('settings.system', 'Configuracion del sistema') !!}
                            </div>
                        </section>
                    @endif
                @endunless

                <section class="nav-module" data-accordion-module="personal">
                    <button type="button" class="nav-module-button" data-accordion-toggle aria-expanded="{{ $openModule === 'personal' ? 'true' : 'false' }}">
                        <span>Panel personal</span><span class="nav-chevron">⌄</span>
                    </button>
                    <div class="nav-module-panel {{ $openModule === 'personal' ? '' : 'hidden' }}">
                        @if($user->hasRole('afiliado'))
                            {!! $navLink('affiliate.panel', 'Panel principal', [], ['affiliate.panel']) !!}
                            {!! $navLink('affiliate.profile.show', 'Mi perfil', [], ['affiliate.profile.*']) !!}
                            {!! $navLink('affiliate.credential.preview', 'Mi credencial', [], ['affiliate.credential.*']) !!}
                            @if($user->user_type === 'affiliate' && $user->is_active && $user->affiliate?->status === 'activo')
                                {!! $navLink('store.catalog.index', 'Mini tienda', [], ['store.catalog.*']) !!}
                                {!! $navLink('store.cart.show', 'Mi carrito ('.collect(session('store_cart.lines', []))->sum('quantity').')', [], ['store.cart.*']) !!}
                                {!! $navLink('store.orders.index', 'Mis pedidos', [], ['store.orders.*', 'store.checkout.*']) !!}
                            @endif
                            <a class="nav-link" href="{{ route('affiliate.profile.show') }}#payments" data-sidebar-link>Mis pagos</a>
                        @endif
                        @if($user->hasRole('accionista'))
                            {!! $navLink('investments.panel', 'Panel del accionista', [], ['investments.panel']) !!}
                        @endif
                        <form method="post" action="{{ route('logout') }}" class="pt-2">
                            @csrf
                            <button class="w-full rounded bg-[#102b4c] px-3 py-2 text-left text-slate-100 hover:bg-[#163b68]" data-sidebar-link>Cerrar sesion</button>
                        </form>
                    </div>
                </section>
            </nav>
        </aside>
        <div class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden" data-sidebar-backdrop></div>
    @endauth

    <main class="flex-1">
        @auth
            <header class="border-b border-slate-200 bg-white px-5 py-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <button type="button" class="rounded bg-[#0b1f3a] px-3 py-2 text-sm font-black text-[#d4af37] lg:hidden" data-sidebar-open>Menu</button>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#b8942f]">{{ auth()->user()->role }}</p>
                            <h1 class="text-2xl font-black text-slate-950">{{ $title ?? 'SIAFCO' }}</h1>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500">{{ auth()->user()->name }}</p>
                </div>
            </header>
        @endauth

        <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            @if(session('status'))
                <div class="mb-5 rounded border border-[#d4af37]/40 bg-[#fff8df] px-4 py-3 text-sm text-slate-900">{{ session('status') }}</div>
            @endif
            @if(session('warning'))
                <div class="mb-5 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950">{{ session('warning') }}</div>
            @endif
            @if(isset($errors) && $errors->any())
                <div class="mb-5 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    <strong>Revise los datos ingresados.</strong>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            {{ $slot }}
        </section>
    </main>
</div>
@stack('scripts')
@if(!empty($credentialAssets))
    @vite('resources/js/credential.js')
@endif
</body>
</html>
