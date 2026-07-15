<x-layouts.app title="Panel accionista">
    <div class="grid gap-4 md:grid-cols-4">
        <div class="metric-card"><p>Acciones activas</p><strong>{{ $investor->activeShares() }}</strong></div>
        <div class="metric-card"><p>Total invertido</p><strong>Bs {{ number_format($investor->lots->sum('invested_capital'), 2) }}</strong></div>
        <div class="metric-card"><p>Pendientes</p><strong>{{ $investor->lots->flatMap->periods->whereIn('status', ['pending','approved'])->count() }}</strong></div>
        <div class="metric-card"><p>Total recibido</p><strong>Bs {{ number_format($investor->receipts->where('status', 'paid')->sum('total_amount'), 2) }}</strong></div>
    </div>
    <div class="section-card mt-5 overflow-x-auto">
        <h2 class="mb-4 text-xl font-black text-[#0b1f3a]">{{ $investor->person->full_name }}</h2>
        <table class="table">
            <thead><tr><th>Lote</th><th>Compra</th><th>Maduracion</th><th>Fin contrato</th><th>Acciones</th><th>Estado</th><th>Rendimiento esperado</th></tr></thead>
            <tbody>
            @foreach($investor->lots as $lot)
                <tr>
                    <td>{{ $lot->purchase_number }}</td>
                    <td>{{ $lot->purchase_date->format('d/m/Y') }}</td>
                    <td>{{ $lot->maturity_date->format('d/m/Y') }}</td>
                    <td>{{ $lot->contract_end_date->format('d/m/Y') }}</td>
                    <td>{{ $lot->shares_quantity }}</td>
                    <td><span class="badge">{{ $lot->status }}</span></td>
                    <td>Bs {{ number_format($lot->invested_capital * $lot->return_percentage / 100, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="section-card mt-5 overflow-x-auto">
        <h2 class="mb-4 text-xl font-black text-[#0b1f3a]">Recibos PDF</h2>
        <table class="table">
            <thead><tr><th>Recibo</th><th>Fecha</th><th>Total</th><th></th></tr></thead>
            <tbody>
            @foreach($investor->receipts as $receipt)
                <tr>
                    <td>{{ $receipt->receipt_number }}</td>
                    <td>{{ $receipt->issue_date->format('d/m/Y') }}</td>
                    <td>Bs {{ number_format($receipt->total_amount, 2) }}</td>
                    <td><a class="font-bold" href="{{ route('investments.receipts.pdf', $receipt) }}">PDF</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
