@php
    $logoSrc = $logoSrc ?? ($institution->logoUrl() ?: null);
    $photoSrc = $photoSrc ?? ($affiliate->photo_path ? Storage::url($affiliate->photo_path) : null);
    $qrSrc = $qrSrc ?? ($credential?->qr_path ? Storage::url($credential->qr_path) : null);
    $institutionName = mb_strtoupper($institution->institution_name ?: 'COOPERATIVA TIERRA BENDITA R.L.');
    $institutionLines = preg_split('/\s+(?=TIERRA\b)/', $institutionName, 2);
    $affiliateName = mb_strtoupper($affiliate->full_name);
    $nameLength = mb_strlen($affiliateName);
    $nameClass = $nameLength > 34 ? 'affiliate-name-small' : ($nameLength > 24 ? 'affiliate-name-medium' : 'affiliate-name-normal');
@endphp

<div
    id="credential-card"
    class="affiliate-card"
    style="--navy: {{ $institution->primary_color ?: '#211d50' }}; --navy-dark: {{ $institution->primary_color ?: '#0c0d50' }}; --gold: {{ $institution->secondary_color ?: '#d49300' }};"
>
    <div class="top-navy-wave"></div>
    <div class="top-gold-wave gold-wave-one"></div>
    <div class="top-gold-wave gold-wave-two"></div>

    <header class="affiliate-card-header">
        <div class="affiliate-logo-box">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="Logo institucional" class="affiliate-logo">
            @else
                <div class="affiliate-logo-placeholder">SIAFCO</div>
            @endif
        </div>

        <div class="affiliate-institution">
            <h1>
                @foreach($institutionLines as $line)
                    <span>{{ $line }}</span>
                @endforeach
            </h1>
        </div>
    </header>

    <div class="affiliate-card-content">
        <section class="affiliate-data">
            <div class="affiliate-title">CARNET DE AFILIADO</div>

            <div class="affiliate-fields">
                <div class="affiliate-row">
                    <span class="affiliate-label">NOMBRE</span>
                    <span class="affiliate-colon">:</span>
                    <span class="affiliate-value affiliate-name {{ $nameClass }}">{{ $affiliateName }}</span>
                </div>

                <div class="affiliate-row">
                    <span class="affiliate-label">ID</span>
                    <span class="affiliate-colon">:</span>
                    <span class="affiliate-value">{{ $affiliate->registration_number }}</span>
                </div>

                <div class="affiliate-row">
                    <span class="affiliate-label">C.I.</span>
                    <span class="affiliate-colon">:</span>
                    <span class="affiliate-value">{{ $affiliate->ci }}</span>
                </div>
            </div>

            <div class="affiliate-qr-box">
                @if($qrSrc)
                    <img src="{{ $qrSrc }}" alt="QR de verificacion publica">
                @endif
            </div>
        </section>

        <aside class="affiliate-photo-box">
            @if($photoSrc)
                <img src="{{ $photoSrc }}" alt="Foto del afiliado" class="affiliate-photo">
            @else
                <div class="affiliate-photo-placeholder">SIN FOTO</div>
            @endif
        </aside>
    </div>

    <div class="affiliate-bottom-bar"></div>
</div>
