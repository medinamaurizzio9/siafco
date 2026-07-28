<x-layouts.app title="Credencial digital" :credential-assets="true">
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
            ])
        </div>

        <div class="credential-actions">
            @if(auth()->user()->role === 'afiliado')
                <a href="{{ route('affiliate.credential.pdf') }}" class="btn-download">Descargar PDF</a>
            @else
                <a href="{{ route('credentials.pdf', $affiliate) }}" class="btn-download">Descargar PDF</a>
            @endif

            <button
                type="button"
                id="download-credential-png"
                class="btn-download"
                data-filename="credencial-{{ $affiliate->registration_number }}.png"
            >
                Descargar PNG
            </button>

            <button type="button" onclick="window.print()" class="btn-print">Imprimir credencial</button>
        </div>
    </div>
</x-layouts.app>
