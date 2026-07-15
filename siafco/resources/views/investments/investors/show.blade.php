<x-layouts.app title="Accionista {{ $investor->investor_number }}">
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="section-card lg:col-span-2">
            <h2 class="mb-4 text-xl font-black text-[#0b1f3a]">{{ $investor->person->full_name }}</h2>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div><dt class="font-bold text-slate-500">CI</dt><dd>{{ $investor->person->ci }}</dd></div>
                <div><dt class="font-bold text-slate-500">Estado</dt><dd><span class="badge">{{ $investor->status }}</span></dd></div>
                <div><dt class="font-bold text-slate-500">Tipo</dt><dd>{{ $investor->type?->name ?? 'Sin clasificar' }}</dd></div>
                <div><dt class="font-bold text-slate-500">Acciones activas</dt><dd>{{ $investor->activeShares() }}</dd></div>
            </dl>
            <div class="mt-5 flex flex-wrap gap-3">
                <a class="btn-secondary" href="{{ route('investments.investors.edit', $investor) }}">Editar</a>
                <a class="btn-primary" href="{{ route('investments.lots.create', ['investor_id' => $investor->id]) }}">Venta de acciones</a>
                <a class="btn-secondary" href="{{ route('investments.reservations.create', ['investor_id' => $investor->id]) }}">Crear reserva</a>
            </div>
        </div>
        <div class="section-card">
            <p class="font-bold text-slate-500">Resumen</p>
            <strong class="mt-2 block text-3xl text-[#0b1f3a]">Bs {{ number_format($investor->lots->sum('invested_capital'), 2) }}</strong>
            <p class="text-sm text-slate-500">Capital registrado</p>
        </div>
    </div>

    <div class="section-card mt-5 overflow-x-auto">
        <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Lotes</h3>
        <table class="table">
            <thead><tr><th>Compra</th><th>Acciones</th><th>Capital</th><th>Maduracion</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @foreach($investor->lots as $lot)
                <tr>
                    <td>{{ $lot->purchase_number }}</td>
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
</x-layouts.app>
