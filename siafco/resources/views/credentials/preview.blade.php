<x-layouts.app :title="auth()->user()->role === 'afiliado' ? 'Mi credencial' : 'Credencial digital'" :credential-assets="true">
    <div class="credential-wrapper">
        @if(auth()->user()->role === 'afiliado')
            <div class="credential-readonly-heading">
                <h1>MI CREDENCIAL</h1>
                <p>Esta es tu credencial digital vigente. Para solicitar una copia descargable o una impresión oficial, comunícate con la Cooperativa Tierra Bendita.</p>
            </div>
        @endif

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
                <a href="{{ route('credentials.png', $affiliate) }}" class="btn-download">Descargar PNG</a>
            @endcan

            @can('printCredential', $affiliate)
                <a href="{{ route('credentials.print', $affiliate) }}" class="btn-print" target="_blank">Imprimir credencial</a>
            @endcan

            @if(auth()->user()->role === 'afiliado')
                <a href="{{ route('affiliate.panel') }}" class="btn-print">Volver al panel</a>
            @endif
        </div>
    </div>

    @if(!empty($printMode))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</x-layouts.app>
