<x-layouts.app title="Accionistas">
    <div class="section-card">
        <form class="mb-4 grid gap-3 md:grid-cols-[1fr_180px_auto]" method="get">
            <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre, CI o numero">
            <select class="form-input" name="status">
                <option value="">Todos</option>
                @foreach(['prospect','reserved','active','suspended','completed','cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="btn-secondary">Filtrar</button>
        </form>
        <div class="mb-4 flex justify-end">
            <a class="btn-primary" href="{{ route('investments.investors.create') }}">Nuevo accionista</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Numero</th><th>Nombre</th><th>CI</th><th>Tipo</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach($investors as $investor)
                    <tr>
                        <td class="font-black">{{ $investor->investor_number }}</td>
                        <td>{{ $investor->person->full_name }}</td>
                        <td>{{ $investor->person->ci }}</td>
                        <td>{{ $investor->type?->name ?? 'Sin clasificar' }}</td>
                        <td><span class="badge">{{ $investor->status }}</span></td>
                        <td><a class="font-bold text-[#0b1f3a]" href="{{ route('investments.investors.show', $investor) }}">Ver</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $investors->links() }}</div>
    </div>
</x-layouts.app>
