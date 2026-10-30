<x-layouts.app title="Liquidaciones de Personal y Comisiones">
@php($periodLabels = ['draft' => 'Borrador', 'closed' => 'Cerrada', 'paid' => 'Pago registrado', 'voided' => 'Anulada'])
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><p class="text-sm font-bold uppercase text-siafco-gold-600">Personal y Comisiones</p><h1 class="text-2xl font-black text-siafco-primary-900">Liquidaciones</h1><p class="text-slate-600">Consulta y seguimiento de liquidaciones y pagos manuales.</p></div>
        @if(auth()->user()->hasPermission('rrhh.payments.prepare'))<x-ui.button href="{{ route('rrhh.reports.periods.create') }}">Calcular liquidación</x-ui.button>@endif
    </div>
    <x-ui.card>
        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-slate-100 text-left"><tr><th class="p-3">Número</th><th class="p-3">Trabajador</th><th class="p-3">Período</th><th class="p-3">Total</th><th class="p-3">Estado</th><th class="p-3">Acciones</th></tr></thead>
            <tbody>@forelse($periods as $period)<tr class="border-t"><td class="p-3 font-bold">{{ $period->number }}</td><td class="p-3">{{ $period->worker->full_name }}</td><td class="p-3">{{ $period->date_from->format('d/m/Y') }} - {{ $period->date_to->format('d/m/Y') }}</td><td class="p-3 whitespace-nowrap">BOB {{ number_format((float) $period->calculated_total, 2) }}</td><td class="p-3">{{ $periodLabels[$period->status] ?? $period->status }}</td><td class="p-3"><x-ui.button variant="outline" href="{{ route('rrhh.reports.periods.show', $period) }}">Ver</x-ui.button></td></tr>@empty<tr><td colspan="6" class="p-6 text-center text-slate-500">No hay liquidaciones.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $periods->links() }}
    </x-ui.card>
</div>
</x-layouts.app>
