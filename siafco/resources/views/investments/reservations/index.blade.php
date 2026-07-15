<x-layouts.app title="Reservas de acciones">
    <div class="section-card">
        <div class="mb-4 flex flex-wrap justify-between gap-3">
            <form method="get" class="flex gap-2">
                <select class="form-input" name="status">
                    <option value="">Todas</option>
                    @foreach(['pending','active','converted','expired','cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>
                    @endforeach
                </select>
                <button class="btn-secondary">Filtrar</button>
            </form>
            <a class="btn-primary" href="{{ route('investments.reservations.create') }}">Nueva reserva</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Accionista</th><th>Acciones</th><th>Total</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach($reservations as $reservation)
                    <tr>
                        <td>{{ $reservation->investor->person->full_name }}</td>
                        <td>{{ $reservation->shares_quantity }}</td>
                        <td>Bs {{ number_format($reservation->total_amount, 2) }}</td>
                        <td>{{ $reservation->expiration_date->format('d/m/Y') }}</td>
                        <td><span class="badge">{{ $reservation->status }}</span></td>
                        <td><a class="font-bold" href="{{ route('investments.reservations.show', $reservation) }}">Ver</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $reservations->links() }}</div>
    </div>
</x-layouts.app>
