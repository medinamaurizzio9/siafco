<x-layouts.app title="Asesor {{ $advisor->advisor_number }}">
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="section-card">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-4">
                    @if($advisor->photoUrl())
                        <img class="h-24 w-24 rounded-full border-4 border-[#d9a514] object-cover" src="{{ $advisor->photoUrl() }}" alt="Fotografía de {{ $advisor->full_name }}">
                    @else
                        <span class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-[#d9a514] bg-[#0b1f3a] text-2xl font-black text-white">{{ $advisor->initials() }}</span>
                    @endif
                    <div>
                    <p class="text-sm font-bold uppercase text-slate-500">{{ $advisor->advisor_number }}</p>
                    <h2 class="mt-1 text-2xl font-black text-[#0b1f3a]">{{ $advisor->full_name }}</h2>
                    </div>
                </div>
                <span class="badge">{{ $advisor->is_active ? 'Activo' : 'Inactivo' }}</span>
            </div>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><dt class="font-bold text-slate-500">Celular</dt><dd>{{ $advisor->phone }}</dd></div>
                <div><dt class="font-bold text-slate-500">Correo</dt><dd>{{ $advisor->email ?: 'Sin correo' }}</dd></div>
                <div><dt class="font-bold text-slate-500">Usuario vinculado</dt><dd>{{ $advisor->user?->name ?? 'Sin vincular' }}</dd></div>
                <div><dt class="font-bold text-slate-500">Fecha de creación</dt><dd>{{ $advisor->created_at->format('d/m/Y H:i') }}</dd></div>
            </dl>
            @if(auth()->user()->hasPermission('investment_advisors.update'))
                <div class="mt-5"><a class="btn-secondary" href="{{ route('investments.advisors.edit', $advisor) }}">Editar asesor</a></div>
            @endif
        </section>

        <section class="section-card text-center">
            <p class="text-sm font-bold uppercase text-slate-500">QR personal del asesor</p>
            <img class="mx-auto mt-4 h-64 w-64 rounded-lg border border-slate-200 bg-white p-3" src="{{ $qrUrl }}" alt="QR personal de {{ $advisor->advisor_number }}">
            <p class="mt-4 text-sm text-slate-600">Este QR será utilizado para registrar prospectos de inversión.</p>
            <label class="form-label mt-4" for="public-url">URL pública</label>
            <input class="form-input text-sm" id="public-url" value="{{ $publicUrl }}" readonly>
            <div class="mt-4 flex flex-wrap justify-center gap-3">
                <button class="btn-secondary" type="button" data-copy-value="{{ $publicUrl }}">Copiar enlace</button>
                <a class="btn-primary" href="{{ route('investments.advisors.qr.download', $advisor) }}">Descargar QR</a>
            </div>
        </section>
    </div>

    <section class="section-card mt-5">
        <h3 class="text-lg font-black text-[#0b1f3a]">Acceso CRM</h3>
        @if($advisor->access)
            <dl class="mt-4 grid gap-3 sm:grid-cols-3"><div><dt class="font-bold text-slate-500">Estado</dt><dd>{{ !$advisor->access->is_enabled ? 'DESACTIVADO' : ($advisor->access->locked_until?->isFuture() ? 'BLOQUEADO' : 'ACTIVO') }}</dd></div><div><dt class="font-bold text-slate-500">Código de acceso</dt><dd>{{ $advisor->access->login_code }}</dd></div><div><dt class="font-bold text-slate-500">Último ingreso</dt><dd>{{ \App\Support\InvestmentCrmDate::format($advisor->access->last_login_at) }}</dd></div></dl>
            <div class="mt-4 flex flex-wrap gap-2"><form method="post" action="{{ route('investments.advisors.access.reset',$advisor) }}">@csrf<button class="btn-secondary">Restablecer PIN</button></form>@if($advisor->access->is_enabled)<form method="post" action="{{ route('investments.advisors.access.disable',$advisor) }}">@csrf<button class="btn-secondary">Desactivar acceso</button></form>@else<form method="post" action="{{ route('investments.advisors.access.enable',$advisor) }}">@csrf<button class="btn-primary">Activar acceso</button></form>@endif @if($advisor->access->locked_until?->isFuture())<form method="post" action="{{ route('investments.advisors.access.unlock',$advisor) }}">@csrf<button class="btn-secondary">Desbloquear</button></form>@endif</div>
        @else
            <p class="mt-3 text-slate-600">Este asesor aún no tiene acceso al portal CRM.</p><form class="mt-4" method="post" action="{{ route('investments.advisors.access.generate',$advisor) }}">@csrf<button class="btn-primary">Generar acceso CRM</button></form>
        @endif
        @if(session('crm_credentials')) @php($credentials=session('crm_credentials')) @php($loginUrl=route('investment-crm.advisor.login')) @php($message="Hola {$advisor->full_name}.\n\nTu acceso al CRM de Inversiones es:\nCódigo: {$credentials['login_code']}\nPIN temporal: {$credentials['pin']}\n\nIngresa aquí: {$loginUrl}\n\nPor seguridad cambia tu PIN al ingresar.")
            <div class="mt-5 rounded-lg border-2 border-amber-400 bg-amber-50 p-4"><p class="font-black">Credenciales visibles una sola vez</p><p>Código: <strong>{{ $credentials['login_code'] }}</strong></p><p>PIN temporal: <strong>{{ $credentials['pin'] }}</strong></p><a class="btn-secondary mt-3 inline-flex" href="https://wa.me/?text={{ urlencode($message) }}" target="_blank" rel="noopener noreferrer">Compartir por WhatsApp</a></div>
        @endif
    </section>

    <section class="section-card mt-5">
        <h3 class="text-lg font-black text-[#0b1f3a]">Calidad de atención</h3>
        <p class="mt-1 text-sm text-slate-500">Valoraciones de prospectos captados originalmente por este asesor.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach($quality as $rating => $item)<div class="rounded-lg bg-slate-50 p-3"><p class="font-bold">{{ \App\Support\InvestmentProspectServiceRating::emoji($rating) }} {{ $item['label'] }}</p><p class="mt-1 text-2xl font-black text-[#0b1f3a]">{{ $item['count'] }}</p></div>@endforeach
            <div class="rounded-lg bg-slate-50 p-3"><p class="font-bold">Sin calificación</p><p class="mt-1 text-2xl font-black text-[#0b1f3a]">{{ $unratedCount }}</p></div>
        </div>
    </section>

    @push('scripts')
        <script>
            document.querySelector('[data-copy-value]')?.addEventListener('click', async (event) => {
                await navigator.clipboard.writeText(event.currentTarget.dataset.copyValue);
                event.currentTarget.textContent = 'Enlace copiado';
            });
        </script>
    @endpush
</x-layouts.app>
