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
<div class="min-h-screen lg:flex">
    @auth
        <aside class="bg-[#0b1f3a] text-white lg:w-72">
            <div class="flex items-center justify-between px-5 py-4 lg:block">
                <a href="{{ auth()->user()->role === 'afiliado' ? route('affiliate.panel') : route('admin.dashboard') }}" class="flex items-center gap-3">
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
            </div>
            <nav class="grid gap-1 px-3 pb-4 text-sm">
                @if(auth()->user()->role === 'afiliado')
                    <a class="nav-link" href="{{ route('affiliate.panel') }}">Panel afiliado</a>
                @else
                    <a class="nav-link" href="{{ route('admin.dashboard') }}">Dashboard</a>
                    <a class="nav-link" href="{{ route('affiliates.index') }}">Afiliados</a>
                    <a class="nav-link" href="{{ route('payments.index') }}">Pagos</a>
                    @if(auth()->user()->hasRole(['administrador', 'administrador_sector', 'secretaria']))
                        <a class="nav-link" href="{{ route('sectors.index') }}">Sectores</a>
                        <a class="nav-link" href="{{ route('plans.index') }}">Planes</a>
                        <a class="nav-link" href="{{ route('institutional-qr.show') }}">QR pago</a>
                        <a class="nav-link" href="{{ route('institutional-settings.edit') }}">Configuracion</a>
                    @endif
                    <a class="nav-link" href="{{ route('reports.index') }}">Reportes</a>
                    <a class="nav-link" href="{{ route('credits.placeholder') }}">Creditos</a>
                @endif
                <form method="post" action="{{ route('logout') }}" class="pt-2">
                    @csrf
                    <button class="w-full rounded bg-[#102b4c] px-3 py-2 text-left text-slate-100 hover:bg-[#163b68]">Cerrar sesion</button>
                </form>
            </nav>
        </aside>
    @endauth

    <main class="flex-1">
        @auth
            <header class="border-b border-slate-200 bg-white px-5 py-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#b8942f]">{{ auth()->user()->role }}</p>
                        <h1 class="text-2xl font-black text-slate-950">{{ $title ?? 'SIAFCO' }}</h1>
                    </div>
                    <p class="text-sm text-slate-500">{{ auth()->user()->name }}</p>
                </div>
            </header>
        @endauth

        <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            @if(session('status'))
                <div class="mb-5 rounded border border-[#d4af37]/40 bg-[#fff8df] px-4 py-3 text-sm text-slate-900">{{ session('status') }}</div>
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
