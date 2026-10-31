<x-simple-investor.layout title="Mis acciones">
    <header class="investor-page-heading"><h1 class="investor-title">Mis acciones</h1><span>{{ $units->count() }} unidades emitidas</span></header>
    <div class="investor-table-wrap overflow-x-auto"><table class="investor-table"><caption class="sr-only">Unidades y contrato de origen</caption>
        <thead><tr><th scope="col">Unidad</th><th scope="col">Contrato de origen</th><th scope="col">Detalle</th></tr></thead>
        <tbody>@forelse($units as $unit)<tr><th scope="row" data-label="Unidad">{{ $unit->code }}</th><td data-label="Contrato">{{ $unit->contract?->contract_number }}</td><td data-label="Detalle"><a class="investor-table-action" href="{{ route('simple-investor.contracts.show',$unit->contract_id) }}">Ver contrato <x-ui.icon name="arrow-right"/></a></td></tr>@empty<tr><td colspan="3">No existen unidades emitidas.</td></tr>@endforelse</tbody>
    </table></div>
</x-simple-investor.layout>
