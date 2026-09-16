<x-layouts.app title="Solicitud recibida">
    <div class="express-shell">
        <section class="express-card express-success">
            <div class="express-check">✓</div>
            <h1>Afiliación registrada</h1>
            <p>Tu pago fue enviado para revisión.</p>
            <div class="express-code">{{ $application->request_code }}</div>
            <x-affiliation-status :status="$application->status" size="sm" />

            <div class="express-access">
                <h2>Cuenta de acceso creada</h2>
                <span>Usuario</span>
                <strong>{{ $accessUser }}</strong>
                <span>Contraseña provisional</span>
                <strong>{{ $temporaryPassword }}</strong>
                <p>Al ingresar por primera vez deberás cambiar tu contraseña y completar los datos faltantes de tu perfil.</p>
            </div>

            <a class="btn-primary min-h-12 w-full" href="{{ route('login') }}">Ingresar a mi cuenta</a>
        </section>
    </div>
</x-layouts.app>
