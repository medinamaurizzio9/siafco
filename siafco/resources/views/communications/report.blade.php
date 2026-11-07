<x-layouts.app title="Estadísticas de lectura">
<section class="card space-y-4 p-5">
<h2 class="text-xl font-bold">{{ $item->title }}</h2><p>{{ $stats->total }} asignados · {{ $stats->opened }} abiertos · {{ $stats->confirmed }} confirmados</p>
<div class="overflow-x-auto"><table class="table"><thead><tr><th>Afiliado</th><th>Asignación</th><th>Primera apertura</th><th>Confirmación</th></tr></thead><tbody>@foreach($rows as $row)<tr><td>{{ $row->full_name }}</td><td>{{ $row->assigned_at }}</td><td>{{ $row->opened_at ?? 'Pendiente' }}</td><td>{{ $row->confirmed_at ?? '—' }}</td></tr>@endforeach</tbody></table></div>{{ $rows->links() }}
</section>
</x-layouts.app>
