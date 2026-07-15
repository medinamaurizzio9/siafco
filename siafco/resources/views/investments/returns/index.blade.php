<x-layouts.app title="Rendimientos mensuales">
    <div class="section-card">
        <form class="mb-4 flex flex-wrap gap-2" method="get">
            <select class="form-input max-w-xs" name="status">
                <option value="">Todos</option>
                @foreach(['upcoming','pending','prepared','pending_approval','approved','paid','rejected','cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="btn-secondary">Filtrar</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Accionista</th><th>Lote</th><th>Base</th><th>Bono</th><th>Total</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach($periods as $period)
                    <tr>
                        <td>{{ $period->due_date->format('d/m/Y') }}</td>
                        <td>{{ $period->lot->investor->person->full_name }}</td>
                        <td>{{ $period->lot->purchase_number }}</td>
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
        <div class="mt-4">{{ $periods->links() }}</div>
    </div>
</x-layouts.app>
