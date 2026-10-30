<x-layouts.app title="Informes de Personal y Comisiones">
@php
    $types = ['management' => 'Resumen gerencial', 'personal' => 'Personal', 'sales' => 'Ventas', 'commissions' => 'Comisiones', 'liquidations' => 'Liquidaciones', 'payments' => 'Pagos registrados', 'adjustments' => 'Ajustes y correcciones'];
    $statuses = ['active' => 'Activo', 'inactive' => 'Inactivo', 'pending' => 'Pendiente', 'confirmed' => 'Confirmado', 'voided' => 'Anulado', 'draft' => 'Borrador', 'closed' => 'Cerrado', 'paid' => 'Pagado', 'applied' => 'Aplicado'];
@endphp
<div class="space-y-6">
    <div><p class="text-sm font-bold uppercase text-siafco-gold-600">Personal y Comisiones</p><h1 class="text-2xl font-black text-siafco-primary-900">Informes</h1><p class="text-slate-600">Consulta histórica, resumen gerencial y exportaciones consistentes.</p></div>

    <x-ui.card>
        <form method="get" action="{{ route('rrhh.reports.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label><span class="form-label">Tipo de informe</span><select class="form-input" name="type">@foreach($types as $value => $label)<option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="form-label">Fecha desde</span><input class="form-input" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label><span class="form-label">Fecha hasta</span><input class="form-input" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <label><span class="form-label">Trabajador</span><select class="form-input" name="worker_id"><option value="">Todos</option>@foreach($workers as $worker)<option value="{{ $worker->id }}" @selected((string)($filters['worker_id'] ?? '') === (string)$worker->id)>{{ $worker->code }} · {{ $worker->full_name }}</option>@endforeach</select></label>
            <label><span class="form-label">Tipo de trabajador</span><select class="form-input" name="worker_type"><option value="">Todos</option><option value="scheduled" @selected(($filters['worker_type'] ?? '') === 'scheduled')>Con horario</option><option value="virtual" @selected(($filters['worker_type'] ?? '') === 'virtual')>Virtual</option></select></label>
            <label><span class="form-label">Producto</span><select class="form-input" name="product_id"><option value="">Todos</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string)($filters['product_id'] ?? '') === (string)$product->id)>{{ $product->code }} · {{ $product->name }}</option>@endforeach</select></label>
            <label><span class="form-label">Estado</span><select class="form-input" name="status"><option value="">Todos</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="form-label">Tipo de ajuste</span><select class="form-input" name="adjustment_type"><option value="">Todos</option><option value="earning" @selected(($filters['adjustment_type'] ?? '') === 'earning')>Bonificación</option><option value="deduction" @selected(($filters['adjustment_type'] ?? '') === 'deduction')>Descuento</option><option value="manual" @selected(($filters['adjustment_type'] ?? '') === 'manual')>Ordinario</option><option value="voided_sale" @selected(($filters['adjustment_type'] ?? '') === 'voided_sale')>Venta anulada</option><option value="review_pending" @selected(($filters['adjustment_type'] ?? '') === 'review_pending')>Revisión pendiente</option></select></label>
            <label><span class="form-label">Búsqueda</span><input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nombre, código o CI"></label>
            <label><span class="form-label">Referencia de pago</span><input class="form-input" name="reference" value="{{ $filters['reference'] ?? '' }}"></label>
            <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-4">
                <x-ui.button type="submit">Consultar</x-ui.button>
                @if(auth()->user()->hasPermission('rrhh.reports.print'))
                    <x-ui.button variant="outline" href="{{ route('rrhh.reports.pdf', $filters) }}">PDF</x-ui.button>
                    <x-ui.button variant="outline" href="{{ route('rrhh.reports.excel', $filters) }}">Excel</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <section aria-labelledby="report-title">
        <div class="mb-4"><h2 id="report-title" class="text-xl font-black text-siafco-primary-900">{{ $title }}</h2><p class="text-sm text-slate-600">Criterio temporal: {{ $criterion }}</p></div>
        @if($totals !== [])<div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@foreach($totals as $label => $value)<div class="rounded-lg border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase text-slate-500">{{ $label }}</p><p class="mt-1 text-xl font-black text-siafco-primary-900">{{ $value }}</p></div>@endforeach</div>@endif
        <x-ui.card>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-siafco-primary-900 text-left text-white"><tr>@foreach($headers as $label)<th class="whitespace-nowrap p-3">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($rows as $row)<tr class="border-t border-slate-200">@foreach($headers as $key => $label)<td class="p-3 {{ in_array($key, $numeric, true) ? 'whitespace-nowrap text-right font-semibold' : '' }}">{{ $row[$key] ?? '—' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($headers) }}" class="p-8 text-center text-slate-500">No existen registros para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div>
            @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator)<div class="mt-4">{{ $rows->links() }}</div>@endif
        </x-ui.card>
    </section>
</div>
</x-layouts.app>
