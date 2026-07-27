<x-layouts.app title="Servicios y beneficios">
    <div class="flex items-end justify-between gap-4">
        <div><h2 class="text-2xl font-black text-[#0b1f3a]">Servicios y beneficios</h2><p class="text-sm text-slate-600">Configura el contenido del panel del afiliado.</p></div>
        <a class="btn-primary" href="{{ route('affiliate-benefits.create') }}">Nuevo</a>
    </div>
    <div class="mt-5 overflow-x-auto bg-white shadow-sm">
        <table class="table"><thead><tr><th>Orden</th><th>Título</th><th>Icono</th><th>Estado</th><th>Pendientes</th><th></th></tr></thead>
        <tbody>@forelse($benefits as $benefit)<tr>
            <td>{{ $benefit->order }}</td><td><strong>{{ $benefit->title }}</strong><br><span class="text-xs text-slate-500">{{ $benefit->description }}</span></td>
            <td>{{ $benefit->icon }}</td><td><span class="badge">{{ $benefit->active ? 'Activo' : 'Inactivo' }}</span></td>
            <td>{{ $benefit->visible_when_pending ? 'Visible bloqueado' : 'Oculto' }}</td>
            <td><div class="flex gap-2"><a class="btn-secondary" href="{{ route('affiliate-benefits.edit',$benefit) }}">Editar</a><form method="post" action="{{ route('affiliate-benefits.destroy',$benefit) }}">@csrf @method('delete')<button class="btn-danger" onclick="return confirm('¿Eliminar este elemento?')">Eliminar</button></form></div></td>
        </tr>@empty<tr><td colspan="6">No hay servicios configurados.</td></tr>@endforelse</tbody></table>
    </div>
</x-layouts.app>
