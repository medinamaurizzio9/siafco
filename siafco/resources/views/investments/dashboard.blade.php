<x-layouts.app title="Dashboard de inversiones">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            'Accionistas' => $metrics['total_investors'],
            'Activos' => $metrics['active_investors'],
            'Acciones vendidas' => $metrics['sold_shares'],
            'Capital invertido' => 'Bs '.number_format($metrics['invested_capital'], 2),
            'Reservas activas' => $metrics['active_reservations'],
            'Reservas vencidas' => $metrics['expired_reservations'],
            'Lotes en espera' => $metrics['waiting_lots'],
            'Lotes generando' => $metrics['earning_lots'],
            'Rendimientos del mes' => 'Bs '.number_format($metrics['month_returns'], 2),
            'Rendimientos pendientes' => $metrics['pending_returns'],
            'Bonos del mes' => 'Bs '.number_format($metrics['month_bonus'], 2),
            'Recibos emitidos' => $metrics['issued_receipts'],
        ] as $label => $value)
            <div class="metric-card">
                <p>{{ $label }}</p>
                <strong>{{ $value }}</strong>
            </div>
        @endforeach
    </div>

    <div class="section-card mt-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-black text-[#0b1f3a]">Capital invertido por mes</h2>
            <a class="btn-primary" href="{{ route('investments.lots.create') }}">Registrar venta</a>
        </div>
        <div class="grid gap-3">
            @forelse($salesByMonth as $row)
                <div class="flex items-center gap-3">
                    <span class="w-28 font-bold text-slate-700">{{ $row->month }}</span>
                    <div class="h-3 flex-1 rounded bg-slate-100">
                        <div class="h-3 rounded bg-[#d4af37]" style="width: {{ min(100, max(4, $row->total / max(1, $salesByMonth->max('total')) * 100)) }}%"></div>
                    </div>
                    <span class="w-36 text-right font-black">Bs {{ number_format($row->total, 2) }}</span>
                </div>
            @empty
                <p class="text-slate-500">Aun no hay ventas registradas.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
