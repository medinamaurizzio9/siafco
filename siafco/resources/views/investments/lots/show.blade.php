<x-layouts.app title="Lote {{ $lot->purchase_number }}">
    <div class="section-card">
        <dl class="grid gap-3 md:grid-cols-4">
            <div><dt class="font-bold text-slate-500">Accionista</dt><dd>{{ $lot->investor->person->full_name }}</dd></div>
            <div><dt class="font-bold text-slate-500">Acciones</dt><dd>{{ $lot->shares_quantity }}</dd></div>
            <div><dt class="font-bold text-slate-500">Capital</dt><dd>Bs {{ number_format($lot->invested_capital, 2) }}</dd></div>
            <div><dt class="font-bold text-slate-500">Retorno</dt><dd>{{ $lot->return_percentage }}%</dd></div>
            <div><dt class="font-bold text-slate-500">Compra</dt><dd>{{ $lot->purchase_date->format('d/m/Y') }}</dd></div>
            <div><dt class="font-bold text-slate-500">Maduracion</dt><dd>{{ $lot->maturity_date->format('d/m/Y') }}</dd></div>
            <div><dt class="font-bold text-slate-500">Fin contrato</dt><dd>{{ $lot->contract_end_date->format('d/m/Y') }}</dd></div>
            <div><dt class="font-bold text-slate-500">Estado</dt><dd><span class="badge">{{ $lot->status }}</span></dd></div>
        </dl>
        @if($lot->status === 'pending_approval')
            <form class="mt-5" method="post" action="{{ route('investments.lots.approve', $lot) }}">@csrf <button class="btn-primary">Aprobar inversion</button></form>
        @endif
    </div>
    <div class="section-card mt-5 overflow-x-auto">
        <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Calendario de rendimientos</h3>
        <table class="table">
            <thead><tr><th>#</th><th>Fecha</th><th>Base</th><th>Bono</th><th>Total</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @foreach($lot->periods->sortBy('period_number') as $period)
                <tr>
                    <td>{{ $period->period_number }}</td>
                    <td>{{ $period->due_date->format('d/m/Y') }}</td>
                    <td>Bs {{ number_format($period->base_return_amount, 2) }}</td>
                    <td>Bs {{ number_format($period->production_bonus_amount, 2) }}</td>
                    <td>Bs {{ number_format($period->total_amount, 2) }}</td>
                    <td><span class="badge">{{ $period->status }}</span></td>
                    <td><a class="font-bold" href="{{ route('investments.returns.show', $period) }}">Gestionar</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
