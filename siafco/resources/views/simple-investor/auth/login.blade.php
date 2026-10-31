<x-simple-investor.layout title="Acceso del inversionista">
    <section class="investor-auth investor-access">
        <div class="investor-access-brand">
            @if($logo = $institution->loginLogoUrl())<img src="{{ $logo }}" alt="{{ $institution->institution_name }}" width="88" height="88">@else<x-ui.icon name="building"/>@endif
            <p class="investor-kicker">SIAFCO · Portal privado</p>
            <p>{{ $institution->institution_name }}</p>
        </div>
        <h1>Mi inversión</h1><p class="investor-intro">Accede a tus contratos, acciones y calendario contractual.</p>
        <form method="POST" action="{{ route('simple-investor.login.store') }}" class="investor-form">@csrf
            <label for="investor-ci">Cédula de identidad<input id="investor-ci" name="ci" value="{{ old('ci') }}" autocomplete="username" maxlength="30" required @if($errors->has('ci')) aria-invalid="true" @endif></label>
            <label for="investor-password">Contraseña<input id="investor-password" type="password" name="password" autocomplete="current-password" maxlength="200" required></label>
            <button type="submit">Ingresar <x-ui.icon name="arrow-right"/></button>
        </form>
        <details class="investor-recovery"><summary>¿Necesitas recuperar tu acceso?</summary><p>Contacta al personal autorizado. Después de verificar tu identidad, podrá restablecer tu acceso con una contraseña temporal que deberás cambiar. Nunca envíes tu contraseña ni documentos por un canal no verificado.</p></details>
        <div class="investor-support"><h2>Soporte al inversionista</h2>
            @if($institution->phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $institution->phone) }}"><x-ui.icon name="users"/>{{ $institution->phone }}</a>@endif
            @if($institution->email && !str_ends_with($institution->email, '.test'))<a href="mailto:{{ $institution->email }}"><x-ui.icon name="inbox"/>{{ $institution->email }}</a>@endif
            @if(!$institution->phone && (!$institution->email || str_ends_with($institution->email, '.test')))<p>Comunícate con tu oficina SIAFCO por los canales institucionales habituales.</p>@endif
        </div>
        <p class="investor-help">El acceso es habilitado por personal autorizado. No se requiere instalar una aplicación.</p>
    </section>
</x-simple-investor.layout>
