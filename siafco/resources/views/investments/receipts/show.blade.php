<x-layouts.app title="Recibo {{ $receipt->receipt_number }}">
    <div class="section-card">
        @include('investments.receipts._receipt', ['receipt' => $receipt])
        <div class="mt-6 flex flex-wrap gap-3">
            <a class="btn-primary" href="{{ route('investments.receipts.pdf', $receipt) }}">Descargar PDF</a>
            <button class="btn-secondary" onclick="window.print()">Imprimir</button>
            @if($receipt->status !== 'voided')
                <form class="flex gap-2" method="post" action="{{ route('investments.receipts.void', $receipt) }}">
                    @csrf
                    <input class="form-input" name="void_reason" placeholder="Motivo de anulacion" required>
                    <button class="btn-danger">Anular</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
