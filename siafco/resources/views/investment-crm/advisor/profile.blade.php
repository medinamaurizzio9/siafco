<x-investment-crm.advisor.layout title="Mi perfil" :advisor="$advisor">
    <div class="crm-profile-shell">
        <section class="crm-card">
            <div class="flex items-center gap-4">
                @if($advisor->photoUrl())
                    <img class="crm-avatar h-16 w-16" src="{{ $advisor->photoUrl() }}" alt="Fotografía de {{ $advisor->full_name }}">
                @else
                    <span class="crm-avatar flex h-16 w-16 items-center justify-center text-xl font-black">{{ $advisor->initials() }}</span>
                @endif
                <div><p class="crm-eyebrow">{{ $advisor->advisor_number }}</p><h1 class="crm-title">{{ $advisor->full_name }}</h1></div>
            </div>
            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div><dt class="crm-muted font-bold">Celular</dt><dd>{{ $advisor->phone }}</dd></div>
                <div><dt class="crm-muted font-bold">Código CRM</dt><dd>{{ $access->login_code }}</dd><small class="crm-muted">Úsalo únicamente para ingresar al portal.</small></div>
                <div><dt class="crm-muted font-bold">Estado</dt><dd><span class="crm-status crm-status--closed">Activo</span></dd></div>
                <div><dt class="crm-muted font-bold">Último acceso</dt><dd>{{ \App\Support\InvestmentCrmDate::format($access->last_login_at) }}</dd></div>
            </dl>
            <a class="crm-button crm-button--primary mt-6 w-full" href="{{ route('investment-crm.advisor.pin.edit') }}">Cambiar PIN</a>
            <form class="mt-3" method="post" action="{{ route('investment-crm.advisor.logout') }}">@csrf<button class="crm-button crm-button--secondary w-full">Salir</button></form>
        </section>

        <section class="crm-card crm-capture-qr-card">
            <p class="crm-eyebrow">QR personal</p>
            <h2 class="crm-section-title mt-1">Mi QR de captación</h2>
            <p class="crm-muted mt-2">Muestra este QR a tu prospecto para que registre sus datos y pueda calificar la atención recibida.</p>
            @if($qrUrl)
                <div class="crm-capture-qr"><img src="{{ $qrUrl }}" alt="QR personal de captación de {{ $advisor->full_name }}"></div>
                <p class="crm-qr-help">Este QR abre tu formulario personal de captación. Los prospectos registrados desde aquí quedarán asociados a tu CRM y podrán calificar la atención recibida.</p>
                <div class="crm-qr-actions">
                    <a class="crm-button crm-button--primary" href="{{ $publicUrl }}" target="_blank" rel="noopener noreferrer">Abrir formulario</a>
                    <button class="crm-button crm-button--secondary" type="button" data-share-link="{{ $publicUrl }}" data-share-title="Información de inversión" data-share-text="Completa tus datos para recibir información de inversión.">Compartir enlace</button>
                    <a class="crm-button crm-button--secondary" href="{{ route('investment-crm.advisor.qr.download') }}">Descargar QR</a>
                </div>
                <p class="crm-share-feedback" data-share-feedback role="status" aria-live="polite"></p>
            @else
                <div class="crm-qr-unavailable" role="status">QR no disponible. Contacta al administrador.</div>
            @endif
        </section>
    </div>
</x-investment-crm.advisor.layout>
