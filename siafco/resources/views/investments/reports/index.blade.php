<x-layouts.app title="Reportes de inversiones">
    <div class="section-card">
        <form class="mb-5 grid gap-3 md:grid-cols-5" method="get">
            <input class="form-input" type="date" name="from" value="{{ request('from') }}">
            <input class="form-input" type="date" name="to" value="{{ request('to') }}">
            <input class="form-input" name="ci" value="{{ request('ci') }}" placeholder="CI">
            <select class="form-input" name="status">
                <option value="">Estado</option>
                @foreach(['prospect','reserved','active','suspended','completed','cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="btn-secondary">Filtrar</button>
        </form>
        <div class="mb-5 grid gap-4 md:grid-cols-4">
            <div class="metric-card"><p>Capital invertido</p><strong>Bs {{ number_format($summary['total_invested'], 2) }}</strong></div>
            <div class="metric-card"><p>Total pagado</p><strong>Bs {{ number_format($summary['total_paid'], 2) }}</strong></div>
            <div class="metric-card"><p>Rendimientos pendientes</p><strong>{{ $summary['pending_returns'] }}</strong></div>
            <div class="metric-card"><p>Reservas activas</p><strong>{{ $summary['active_reservations'] }}</strong></div>
        </div>
        <div class="mb-4 flex gap-3">
            <a class="btn-primary" href="{{ route('investments.reports.pdf', request()->query()) }}">Exportar PDF</a>
            <a class="btn-secondary" href="{{ route('investments.reports.csv', request()->query()) }}">Exportar CSV</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Accionista</th><th>CI</th><th>Numero</th><th>Estado</th><th>Acciones</th><th>Capital</th></tr></thead>
                <tbody>
                @foreach($investors as $investor)
                    <tr>
                        <td>{{ $investor->person->full_name }}</td>
                        <td>{{ $investor->person->ci }}</td>
                        <td>{{ $investor->investor_number }}</td>
                        <td><span class="badge">{{ $investor->status }}</span></td>
                        <td>{{ $investor->lots->sum('shares_quantity') }}</td>
                        <td>Bs {{ number_format($investor->lots->sum('invested_capital'), 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $investors->links() }}</div>
    </div>
</x-layouts.app>
