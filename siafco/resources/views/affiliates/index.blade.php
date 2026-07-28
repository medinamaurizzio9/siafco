<x-layouts.app title="Afiliados">
    <form class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-[1fr_220px_auto_auto]" method="get">
        <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre, CI o registro">
        <select class="form-input" name="status">
            <option value="">Todos los estados</option>
            @foreach(['pendiente_pago','activo','inactivo','observado'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\AffiliationStatusPresenter::label($status) }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Filtrar</button>
        @if(auth()->user()->hasRole(['administrador','administrador_sector','secretaria']))
            <a class="btn-primary" href="{{ route('affiliates.create') }}">Nuevo afiliado</a>
        @endif
    </form>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Registro</th><th>Nombre</th><th>CI</th><th>Sector</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach($affiliates as $affiliate)
                    <tr>
                        <td class="font-black">{{ $affiliate->registration_number }}</td>
                        <td>{{ $affiliate->full_name }}</td>
                        <td>{{ $affiliate->ci }}</td>
                        <td>{{ $affiliate->sector->name }}</td>
                        <td><x-affiliation-status :status="$affiliate->status" size="sm" /></td>
                        <td class="text-right"><a class="btn-secondary" href="{{ route('affiliates.show', $affiliate) }}">Ver</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $affiliates->links() }}</div>
</x-layouts.app>
