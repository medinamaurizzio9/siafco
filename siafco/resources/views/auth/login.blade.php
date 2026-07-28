@php($appearance = $institution->loginAppearance())
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar | SIAFCO</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div
    class="login-page"
    style="--login-overlay-opacity: {{ $appearance['overlay_opacity'] }}; --login-background-image: url('{{ $appearance['background_url'] }}');"
>
    <div class="login-page__overlay" aria-hidden="true"></div>

    <main class="login-layout">
        <section class="login-brand" aria-labelledby="login-title">
            @if($appearance['logo_url'])
                <img
                    src="{{ $appearance['logo_url'] }}"
                    alt="Logotipo de {{ $appearance['institution_name'] }}"
                    class="login-brand__logo"
                >
            @else
                <div class="login-brand__logo-fallback" aria-hidden="true">S</div>
            @endif

            <p class="login-brand__eyebrow">Plataforma institucional</p>
            <h1 id="login-title">{{ $appearance['title'] }}</h1>
            <h2>{{ $appearance['institution_name'] }}</h2>
            <p class="login-brand__message">{{ $appearance['affiliate_message'] }}</p>

            <div class="login-benefits" aria-label="Servicios disponibles">
                <div><span aria-hidden="true">✓</span> Consulta el estado de tu afiliación</div>
                <div><span aria-hidden="true">✓</span> Descarga tu credencial digital</div>
                <div><span aria-hidden="true">✓</span> Accede a servicios y beneficios</div>
            </div>
        </section>

        <section class="login-panel" aria-labelledby="login-form-title">
            <form method="post" action="{{ route('login.post') }}" class="login-card">
                @csrf
                <div class="login-card__header">
                    <p class="login-card__eyebrow">Acceso seguro</p>
                    <h2 id="login-form-title">INICIAR SESIÓN</h2>
                    <p>Ingresa tus datos para acceder a tu cuenta.</p>
                </div>

                <div>
                    <label class="form-label" for="email">CORREO ELECTRÓNICO</label>
                    <div class="login-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                            <path d="m3 7 9 6 9-6"></path>
                        </svg>
                        <input
                            id="email"
                            class="form-input login-input @error('email') border-red-500 bg-red-50 @enderror"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            aria-describedby="email-error"
                            required
                            autofocus
                        >
                    </div>
                    <x-forms.field-error name="email" />
                </div>

                <div class="mt-5 login-password-field">
                    <x-password-input
                        name="password"
                        label="CONTRASEÑA"
                        autocomplete="current-password"
                    />
                </div>

                <div class="login-options">
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        <span>Recordarme</span>
                    </label>
                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                    @endif
                </div>

                <button class="login-submit" type="submit">INGRESAR AL SISTEMA</button>
            </form>
        </section>
    </main>

    <footer class="login-footer">
        <span>© {{ now()->year }} {{ $appearance['institution_name'] }}</span>
        <span>Sistema Integral de Afiliación</span>
        <span>www.cooperativatierrabendita.com</span>
    </footer>
</div>
</body>
</html>
