@php
    use App\Support\AffiliationStatusPresenter;

    $logoSrc = $logoSrc ?? ($institution->logoUrl() ?: null);
    $photoSrc = $photoSrc ?? ($affiliate->photo_path ? Storage::url($affiliate->photo_path) : null);
    $qrSrc = $qrSrc ?? ($credential?->qr_path ? Storage::url($credential->qr_path) : null);
    $institutionName = mb_strtoupper($institution->institution_name ?: 'COOPERATIVA TIERRA BENDITA');
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
    <div class="credential-security-border" aria-hidden="true">
        COOPERATIVA TIERRA BENDITA • SIAFCO • CREDENCIAL OFICIAL • COOPERATIVA TIERRA BENDITA • SIAFCO • CREDENCIAL OFICIAL
    </div>
    <span class="credential-corner credential-corner-top-left" aria-hidden="true"></span>
    <span class="credential-corner credential-corner-top-right" aria-hidden="true"></span>
    <span class="credential-corner credential-corner-bottom-left" aria-hidden="true"></span>
    <span class="credential-corner credential-corner-bottom-right" aria-hidden="true"></span>

    @if($logoSrc)
        <div class="credential-watermark" aria-hidden="true">
            <img src="{{ $logoSrc }}" alt="">
        </div>
    @endif

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
        <div class="credential-header-id">
            <span>ID AFILIADO</span>
            <strong>#{{ $credentialData['affiliate_number'] }}</strong>
        </div>
    </header>

    <main class="credential-body">
        <section class="credential-information">
            <div class="credential-title">CREDENCIAL DE AFILIADO</div>

            <div class="credential-data-grid">
                <div class="credential-primary-data">
                    <div class="credential-field credential-field-name">
                        <span>NOMBRE COMPLETO</span>
                        <strong>{{ $credentialData['full_name'] }}</strong>
                    </div>
                    <div class="credential-field credential-field-level-two">
                        <span>NÚMERO DE AFILIADO</span>
                        <strong>{{ $credentialData['affiliate_number'] }}</strong>
                    </div>
                    @if(
                        filled($credentialData['registration_number'] ?? null) &&
                        $credentialData['registration_number'] !== $credentialData['affiliate_number']
                    )
                        <div class="credential-field">
                            <span>NÚMERO DE REGISTRO</span>
                            <strong>{{ $credentialData['registration_number'] }}</strong>
                        </div>
                    @endif
                    <div class="credential-field credential-field-level-two">
                        <span>SECTOR</span>
                        <strong>{{ $credentialData['sector'] }}</strong>
                    </div>
                    <div class="credential-field">
                        <span>INSTITUCIÓN</span>
                        <strong>{{ $credentialData['institution'] }}</strong>
                    </div>
                </div>

                <div class="credential-secondary-data">
                    <div class="credential-field credential-field-level-two">
                        <span>CÉDULA DE IDENTIDAD</span>
                        <strong>{{ $credentialData['identity_document'] }}</strong>
                    </div>
                    <div class="credential-field">
                        <span>REGIONAL</span>
                        <strong>{{ $credentialData['regional'] }}</strong>
                    </div>
                    <div class="credential-field credential-field-meta credential-field-date">
                        <span>FECHA DE EMISIÓN</span>
                        <strong>{{ $credentialData['issued_at'] }}</strong>
                    </div>
                </div>
            </div>

            <div class="credential-verification">
                <div class="credential-qr">
                    @if($qrSrc)
                        <img src="{{ $qrSrc }}" alt="Código QR de verificación pública">
                    @endif
                </div>
                <div class="credential-qr-copy">
                    <strong class="credential-qr-heading">
                        <span class="credential-phone-icon" aria-hidden="true"></span>
                        ESCANEA PARA VERIFICAR
                    </strong>
                    <p>Verifica la autenticidad<br>de esta credencial.</p>
                </div>
            </div>
        </section>

        <aside class="credential-photo-column">
            <div class="credential-photo-label">FOTOGRAFÍA</div>
            <div class="credential-photo-frame">
                @if($photoSrc)
                    <img src="{{ $photoSrc }}" alt="Fotografía del afiliado" class="credential-photo">
                @else
                    <div class="credential-photo-placeholder">SIN FOTO</div>
                @endif
            </div>
            <div class="credential-status credential-status-{{ $statusTone }}">
                <span aria-hidden="true">✓</span>
                {{ $credentialData['status_label'] }}
            </div>
        </aside>
    </main>

    <div class="credential-hologram" aria-hidden="true">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" alt="">
        @else
            <span>✓</span>
        @endif
    </div>

    <div class="credential-microtext" aria-hidden="true">
        COOPERATIVA TIERRA BENDITA · COOPERATIVA TIERRA BENDITA · COOPERATIVA TIERRA BENDITA · COOPERATIVA TIERRA BENDITA
    </div>

    <footer class="credential-footer">
        <span class="credential-footer-version">Versión: {{ $credentialData['version'] }}</span>
        <span class="credential-footer-validity">Válida mientras la afiliación permanezca activa.</span>
        <span class="credential-footer-website">{{ $credentialData['institutional_website'] }}</span>
    </footer>
</div>
