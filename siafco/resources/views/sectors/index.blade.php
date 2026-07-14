<x-layouts.app title="Sectores">
    <div class="mb-4 flex justify-end">
        <a class="btn-primary" href="{{ route('sectors.create') }}">Nuevo sector</a>
    </div>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Codigo</th><th>Nombre</th><th>Regional</th><th>Correlativo</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach($sectors as $sector)
                    <tr>
                        <td class="font-black">{{ $sector->code }}</td>
                        <td>{{ $sector->name }}</td>
                        <td>{{ $sector->regional }}</td>
                        <td>{{ str_pad($sector->current_sequence, 6, '0', STR_PAD_LEFT) }}</td>
                        <td><span class="badge">{{ $sector->is_active ? 'activo' : 'inactivo' }}</span></td>
                        <td class="text-right">
                            <a class="btn-secondary" href="{{ route('sectors.edit', $sector) }}">Editar</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $sectors->links() }}</div>
</x-layouts.app>
