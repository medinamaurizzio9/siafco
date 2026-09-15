<x-layouts.app :title="auth()->user()->role === 'afiliado' ? 'Mi credencial' : 'Credencial digital'" :credential-assets="true">
    @if(auth()->user()->role === 'afiliado')
        <div class="affiliate-screen">
            <header class="affiliate-page-title">
                <h2>MI CREDENCIAL</h2>
                <p>Tu credencial digital siempre contigo.</p>
            </header>

            <section class="affiliate-card affiliate-credential-full">
                <div class="credential-canvas" id="credential-canvas">
                    @include('credenciales.card', [
                        'affiliate' => $affiliate,
                        'credential' => $credential,
                        'credentialData' => $credentialData,
                        'institution' => $institution,
                        'logoSrc' => $institution->logoUrl(),
                        'photoSrc' => $affiliate->photo_path ? Storage::url($affiliate->photo_path) : null,
                        'qrSrc' => Storage::url($credential->qr_path),
                        'mode' => !empty($printMode) ? 'print' : 'web',
                    ])
                </div>
            </section>

            <div class="affiliate-credential-actions">
                <button class="btn-primary min-h-12 w-full" type="button" data-credential-large>
                    <x-ui.icon name="eye" class="h-5 w-5" /> Ver en grande
                </button>
                @can('downloadCredential', $affiliate)
                    <a href="{{ route('affiliate.credential.pdf') }}" class="btn-secondary min-h-12"><x-ui.icon name="download" class="h-5 w-5" /> Descargar</a>
                @endcan
                <button class="btn-secondary min-h-12" type="button" data-share-credential data-share-title="SIAFCO" data-share-text="Credencial digital SIAFCO" data-share-url="{{ route('affiliate.credential.preview') }}">
                    <x-ui.icon name="share" class="h-5 w-5" /> Compartir
                </button>
            </div>

            <p class="affiliate-note"><x-ui.icon name="check" class="h-5 w-5" /> Esta credencial es personal e intransferible.</p>
            <a class="btn-secondary min-h-12 w-full" href="{{ route('affiliate.panel') }}">Volver al panel</a>
        </div>
    @else
        <div class="credential-wrapper">
            <div class="credential-canvas" id="credential-canvas">
                @include('credenciales.card', [
                    'affiliate' => $affiliate,
                    'credential' => $credential,
                    'credentialData' => $credentialData,
                    'institution' => $institution,
                    'logoSrc' => $institution->logoUrl(),
                    'photoSrc' => $affiliate->photo_path ? Storage::url($affiliate->photo_path) : null,
                    'qrSrc' => Storage::url($credential->qr_path),
                    'mode' => !empty($printMode) ? 'print' : 'web',
                ])
            </div>
            <div class="credential-actions">
                @can('downloadCredential', $affiliate)
                    <a href="{{ route('credentials.pdf', $affiliate) }}" class="btn-download">Descargar PDF</a>
                    @if($exportCapabilities->canExportPng())
                        <a href="{{ route('credentials.png', $affiliate) }}" class="btn-download">Descargar PNG</a>
                    @endif
                @endcan
                @can('printCredential', $affiliate)
                    <a href="{{ route('credentials.print', $affiliate) }}" class="btn-print" target="_blank">Imprimir credencial</a>
                @endcan
            </div>
            @can('downloadCredential', $affiliate)
                @if(!$exportCapabilities->canExportPng())
                    <p class="credential-export-notice">PNG no disponible en este servidor.</p>
                @endif
            @endcan
        </div>
    @endif

    @if(!empty($printMode))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</x-layouts.app>
