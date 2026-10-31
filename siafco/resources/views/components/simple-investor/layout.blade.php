@props(['title' => 'Mi inversión'])
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#082544"><meta name="robots" content="noindex,nofollow"><title>{{ $title }} · SIAFCO</title><link rel="manifest" href="/investor-pwa/manifest.webmanifest">@vite(['resources/css/app.css','resources/js/app.js'])</head>
@php($hasNavigation = session()->has('simple_investor_access_id') && !request()->routeIs('simple-investor.password.*'))
<body class="investor-portal {{ $hasNavigation ? 'investor-authenticated' : '' }}"><a class="investor-skip" href="#investor-content">Ir al contenido</a><div class="investor-shell">
<header class="investor-header"><a href="{{ route($hasNavigation ? 'simple-investor.home' : 'simple-investor.login') }}" class="investor-brand"><b>SIAFCO</b><small>MI INVERSIÓN</small></a>@if(session()->has('simple_investor_access_id'))<form method="POST" action="{{ route('simple-investor.logout') }}">@csrf<button type="submit" class="investor-logout">Salir</button></form>@endif</header>
<main class="investor-main" id="investor-content" tabindex="-1">@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif @if($errors->any())<div class="investor-error" role="alert">{{ $errors->first() }}</div>@endif {{ $slot }}</main>
@if($hasNavigation)
    <nav class="investor-nav" aria-label="Navegación principal">
        @foreach([
            ['home', 'Inicio', 'home', request()->routeIs('simple-investor.home')],
            ['contracts', 'Contratos', 'file-text', request()->routeIs('simple-investor.contracts*')],
            ['units', 'Acciones', 'package', request()->routeIs('simple-investor.units')],
            ['calendar', 'Calendario', 'calendar', request()->routeIs('simple-investor.calendar')],
            ['notifications', 'Avisos', 'bell', request()->routeIs('simple-investor.notifications*')],
            ['profile', 'Perfil', 'user', request()->routeIs('simple-investor.profile*')],
        ] as [$route, $label, $icon, $active])
            <a class="{{ $active ? 'active' : '' }}" href="{{ route('simple-investor.'.$route) }}" @if($active) aria-current="page" @endif><x-ui.icon :name="$icon"/><span>{{ $label }}</span></a>
        @endforeach
    </nav>
@endif
</div><script>if('serviceWorker'in navigator&&(location.protocol==='https:'||location.hostname==='localhost'||location.hostname==='127.0.0.1')){navigator.serviceWorker.getRegistrations().then(registrations=>Promise.all(registrations.filter(r=>r.scope===location.origin+'/investor-pwa/'&&r.active?.scriptURL===location.origin+'/investor-pwa/sw.js').map(r=>r.unregister()))).then(()=>navigator.serviceWorker.register('/mi-inversion/sw.js',{scope:'/mi-inversion/',updateViaCache:'none'})).catch(()=>{});}</script></body></html>
