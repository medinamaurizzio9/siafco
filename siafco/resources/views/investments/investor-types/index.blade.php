<x-layouts.app title="Tipos de inversionista">
    <div class="section-card">
        <div class="mb-4 flex justify-end"><a class="btn-primary" href="{{ route('investments.investor-types.create') }}">Nuevo tipo</a></div>
        <table class="table">
            <thead><tr><th>Nombre</th><th>Acciones</th><th>Estado</th><th>Orden</th><th></th></tr></thead>
            <tbody>
            @foreach($types as $type)
                <tr>
                    <td>{{ $type->name }}</td>
                    <td>{{ $type->shares_quantity }}</td>
                    <td><span class="badge">{{ $type->active ? 'activo' : 'inactivo' }}</span></td>
                    <td>{{ $type->order }}</td>
                    <td><a class="font-bold" href="{{ route('investments.investor-types.edit', $type) }}">Editar</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $types->links() }}</div>
    </div>
</x-layouts.app>
