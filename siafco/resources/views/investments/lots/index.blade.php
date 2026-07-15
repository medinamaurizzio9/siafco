<x-layouts.app title="Lotes de inversion">
    <div class="section-card">
        <div class="mb-4 flex flex-wrap justify-between gap-3">
            <form method="get" class="flex gap-2">
                <select class="form-input" name="status">
                    <option value="">Todos</option>
                    @foreach(['pending_approval','active_waiting','active_earning','completed','renewed','suspended','cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>
                    @endforeach
                </select>
                <button class="btn-secondary">Filtrar</button>
            </form>
            <a class="btn-primary" href="{{ route('investments.lots.create') }}">Venta de acciones</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Lote</th><th>Accionista</th><th>Acciones</th><th>Capital</th><th>Maduracion</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach($lots as $lot)
                    <tr>
                        <td class="font-black">{{ $lot->purchase_number }}</td>
                        <td>{{ $lot->investor->person->full_name }}</td>
                        <td>{{ $lot->shares_quantity }}</td>
                        <td>Bs {{ number_format($lot->invested_capital, 2) }}</td>
                        <td>{{ $lot->maturity_date->format('d/m/Y') }}</td>
                        <td><span class="badge">{{ $lot->status }}</span></td>
                        <td><a class="font-bold" href="{{ route('investments.lots.show', $lot) }}">Ver</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $lots->links() }}</div>
    </div>
</x-layouts.app>
