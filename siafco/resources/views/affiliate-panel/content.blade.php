<span class="sr-only">affiliate-panel-content {{ $affiliate?->registration_number }}</span>
@php
    $photoUrl = $affiliate->photo_path ? Storage::disk('public')->url($affiliate->photo_path) : null;
    $credentialState = $affiliate->credential ? 'Vigente' : 'En preparación';
    $latestPaymentDate = $latestPayment ? ($latestPayment->payment_date ?: $latestPayment->paid_at ?: $latestPayment->created_at)?->format('d/m/Y') : 'Sin pagos registrados';
    $quickActions = [
        ['title' => 'Mi credencial', 'copy' => 'Tu identificación digital', 'route' => route('affiliate.credential.preview'), 'icon' => 'credit-card'],
        ['title' => 'Mis pagos', 'copy' => 'Consulta tu historial', 'route' => route('affiliate.profile.show').'#payments', 'icon' => 'receipt'],
        ['title' => 'Beneficios', 'copy' => 'Convenios y descuentos', 'route' => route('affiliate.benefits'), 'icon' => 'gift'],
        ['title' => 'Mi perfil', 'copy' => 'Actualiza tus datos', 'route' => route('affiliate.profile.show'), 'icon' => 'user'],
    ];
@endphp
<div class="affiliate-screen">
    <section class="affiliate-hero">
        <div class="affiliate-hero__copy">
            <p>¡Hola,</p>
            <h2>{{ $affiliate->full_name }}!</h2>
            <span>Juntos construimos un mejor futuro</span>
        </div>
        <div class="affiliate-hero__photo">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="Fotografía de {{ $affiliate->full_name }}">
            @else
                <span>{{ mb_substr($affiliate->full_name, 0, 1) }}</span>
            @endif
        </div>
    </section>

    <section class="affiliate-summary-card">
        <div class="affiliate-status-pill"><x-ui.icon name="check" class="h-4 w-4" /> {{ \App\Support\AffiliationStatusPresenter::label($affiliate->status) }}</div>
        <dl>
            <div><dt>Registro</dt><dd>{{ $affiliate->registration_number ?: 'Pendiente' }}</dd></div>
            <div><dt>Sector</dt><dd>{{ $affiliate->sector?->name ?: 'No registrado' }}</dd></div>
            <div><dt>Regional</dt><dd>{{ $affiliate->regional ?: 'No registrada' }}</dd></div>
        </dl>
    </section>

    <section class="affiliate-kpis" aria-label="Indicadores">
        <article><x-ui.icon name="receipt" class="h-5 w-5" /><span>Pagos registrados</span><strong>{{ $affiliate->payments_count }}</strong></article>
        <article><x-ui.icon name="calendar" class="h-5 w-5" /><span>Último pago</span><strong>{{ $latestPaymentDate }}</strong></article>
        <article><x-ui.icon name="credit-card" class="h-5 w-5" /><span>Credencial</span><strong>{{ $credentialState }}</strong></article>
    </section>

    <section>
        <div class="affiliate-section-heading">
            <h3>Acciones rápidas</h3>
            <a href="{{ route('affiliate.profile.show') }}">Ver todo</a>
        </div>
        <div class="affiliate-actions-grid">
            @foreach($quickActions as $action)
                <a class="affiliate-action-card" href="{{ $action['route'] }}">
                    <span><x-ui.icon :name="$action['icon']" class="h-5 w-5" /></span>
                    <strong>{{ $action['title'] }}</strong>
                    <small>{{ $action['copy'] }}</small>
                    <x-ui.icon name="arrow-right" class="affiliate-action-card__chevron h-4 w-4" />
                </a>
            @endforeach
        </div>
    </section>

    <section class="affiliate-feature-card">
        <div><h3>Mis servicios y beneficios</h3><p>Salud, bienestar y oportunidades.</p></div>
        <a href="{{ route('affiliate.benefits') }}" aria-label="Ver beneficios"><x-ui.icon name="arrow-right" class="h-5 w-5" /></a>
    </section>

    @if($activeAffiliateStore)
        <section class="affiliate-feature-card affiliate-feature-card--store">
            <div><h3>Mini tienda</h3><p>Productos y servicios disponibles para afiliados activos.</p></div>
            <a href="{{ route('store.catalog.index') }}" aria-label="Abrir mini tienda"><x-ui.icon name="arrow-right" class="h-5 w-5" /></a>
        </section>
    @endif

    @if($affiliate->credential)
        <section class="affiliate-card affiliate-credential-preview">
            <div class="affiliate-section-heading"><h3>Credencial digital</h3><span>{{ $credentialState }}</span></div>
            <div class="credential-canvas pointer-events-none mt-4" id="credential-canvas">
                @include('credenciales.card', ['affiliate' => $affiliate, 'credential' => $affiliate->credential, 'credentialData' => $credentialData, 'institution' => $credentialInstitution, 'mode' => 'thumbnail'])
            </div>
            <a class="btn-primary mt-4 min-h-12 w-full" href="{{ route('affiliate.credential.preview') }}">VER MI CREDENCIAL</a>
        </section>
    @endif
</div>
