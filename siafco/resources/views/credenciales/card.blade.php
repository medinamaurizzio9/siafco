@php
    use App\Support\AffiliationStatusPresenter;

    $logoSrc = $logoSrc ?? ($institution->logoUrl() ?: null);
    $photoSrc = $photoSrc ?? ($affiliate->photo_path ? Storage::url($affiliate->photo_path) : null);
    $qrSrc = $qrSrc ?? ($credential?->qr_path ? Storage::url($credential->qr_path) : null);
    $institutionName = mb_strtoupper($institution->institution_name ?: 'COOPERATIVA TIERRA BENDITA');
    $affiliateName = mb_strtoupper($affiliate->full_name);
    $statusLabel = mb_strtoupper(AffiliationStatusPresenter::label($affiliate->status));
    $statusTone = match (strtolower($affiliate->status)) {
        'activo', 'active', 'confirmado', 'confirmed' => 'active',
        'suspendido', 'suspended' => 'suspended',
        default => 'pending',
    };
@endphp

<div
    id="credential-card"
    class="credential-card"
    style="--credential-navy: {{ $institution->primary_color ?: '#0B1F3A' }}; --credential-gold: {{ $institution->secondary_color ?: '#D8A928' }};"
>
    <div class="credential-watermark" aria-hidden="true">SIAFCO</div>

    <header class="credential-header">
        <div class="credential-logo">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="Logo institucional">
            @else
                <span>SIAFCO</span>
            @endif
        </div>
        <div class="credential-institution">
            <h1>{{ $institutionName }}</h1>
            <p>SISTEMA INTEGRAL DE AFILIACIÓN</p>
        </div>
    </header>

    <div class="credential-body">
        <section class="credential-information">
            <div class="credential-title">CREDENCIAL DE AFILIADO</div>

            <div class="credential-fields">
                <div class="credential-field credential-field-wide">
                    <span>NOMBRE COMPLETO</span>
                    <strong>{{ $affiliateName }}</strong>
                </div>
                <div class="credential-field">
                    <span>NÚMERO DE AFILIADO</span>
                    <strong>{{ $affiliate->registration_number }}</strong>
                </div>
                <div class="credential-field">
                    <span>CÉDULA DE IDENTIDAD</span>
                    <strong>{{ mb_strtoupper($affiliate->ci) }}</strong>
                </div>
                <div class="credential-field">
                    <span>SECTOR</span>
                    <strong>{{ mb_strtoupper($affiliate->sector?->name ?? 'NO REGISTRADO') }}</strong>
                </div>
                <div class="credential-field">
                    <span>REGIONAL</span>
                    <strong>{{ mb_strtoupper($affiliate->regional ?: 'NO REGISTRADO') }}</strong>
                </div>
                <div class="credential-field credential-field-wide">
                    <span>INSTITUCIÓN</span>
                    <strong>{{ mb_strtoupper($affiliate->institution ?: $institution->institution_name ?: 'NO REGISTRADO') }}</strong>
                </div>
            </div>

            <div class="credential-verification">
                <div class="credential-qr">
                    @if($qrSrc)
                        <img src="{{ $qrSrc }}" alt="Código QR de verificación pública">
                    @endif
                </div>
                <div>
                    <p class="credential-qr-title">ESCANEA PARA VERIFICAR</p>
                    <p class="credential-qr-text">Consulta la validez de esta credencial en línea.</p>
                </div>
            </div>
        </section>

        <aside class="credential-photo-column">
            <div class="credential-photo-frame">
                @if($photoSrc)
                    <img src="{{ $photoSrc }}" alt="Fotografía del afiliado" class="credential-photo">
                @else
                    <div class="credential-photo-placeholder">SIN FOTO</div>
                @endif
            </div>
            <div class="credential-status credential-status-{{ $statusTone }}">
                <span aria-hidden="true">✓</span>
                {{ $statusLabel }}
            </div>
        </aside>
    </div>

    <footer class="credential-footer">
        <span>Válida mientras la afiliación permanezca activa.</span>
        <span>siafco.viankagold.com</span>
    </footer>
</div>
