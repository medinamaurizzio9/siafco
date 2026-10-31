<x-simple-investor.layout title="Contrato">
    <a class="investor-back" href="{{ route('simple-investor.contracts') }}">Volver a mis contratos</a>
    <h1 class="investor-title">{{ $contract->contract_number }}</h1>
    <section class="investor-card"><dl class="investor-data">
        <div><dt>Firma</dt><dd>{{ $contract->signed_on?->format('d/m/Y') ?: 'Pendiente' }}</dd></div>
        <div><dt>Unidades</dt><dd>{{ $contract->quantity }}</dd></div>
        <div><dt>Precio histórico</dt><dd>{{ $contract->currency }} {{ $contract->unit_price_snapshot }}</dd></div>
        <div><dt>Total contractual</dt><dd>{{ $contract->currency }} {{ $contract->total_amount }}</dd></div>
        <div><dt>Estado</dt><dd><x-simple-investor.contract-status :status="$contract->status"/></dd></div>
    </dl>
    @if($contract->currentDocument?->status==='verified')<a class="investor-button" href="{{ route('simple-investor.documents.download',[$contract,$contract->currentDocument]) }}">Descargar documento verificado</a>@else<p class="investor-help">El documento firmado estará disponible cuando sea verificado.</p>@endif
    </section>
</x-simple-investor.layout>
