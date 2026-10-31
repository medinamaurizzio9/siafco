<x-simple-investor.layout title="Mis contratos">
    <header class="investor-page-heading"><h1 class="investor-title">Mis contratos</h1><span>{{ $contracts->count() }} registrados</span></header>
    <div class="investor-table-wrap overflow-x-auto"><table class="investor-table"><caption class="sr-only">Contratos del inversionista</caption>
        <thead><tr><th scope="col">Contrato</th><th scope="col">Firma</th><th scope="col">Unidades</th><th scope="col">Total contractual</th><th scope="col">Estado</th><th scope="col">Detalle</th></tr></thead>
        <tbody>@forelse($contracts as $contract)<tr>
            <th scope="row" data-label="Contrato">{{ $contract->contract_number }}<small>{{ $contract->product_name_snapshot }}</small></th>
            <td data-label="Firma">{{ $contract->signed_on?->format('d/m/Y') ?: 'Pendiente' }}</td>
            <td data-label="Unidades">{{ $contract->quantity }}</td>
            <td data-label="Total contractual">{{ $contract->currency }} {{ $contract->total_amount }}</td>
            <td data-label="Estado"><x-simple-investor.contract-status :status="$contract->status"/></td>
            <td data-label="Detalle"><a class="investor-table-action" aria-label="Ver contrato {{ $contract->contract_number }}" href="{{ route('simple-investor.contracts.show',$contract) }}">Ver detalle <x-ui.icon name="arrow-right"/></a></td>
        </tr>@empty<tr><td colspan="6">Aún no tienes contratos.</td></tr>@endforelse</tbody>
    </table></div>
</x-simple-investor.layout>
