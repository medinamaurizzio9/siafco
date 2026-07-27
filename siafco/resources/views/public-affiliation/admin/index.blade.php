<x-layouts.app title="Solicitudes públicas">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><h2 class="text-2xl font-black text-[#0b1f3a]">Solicitudes públicas</h2><p class="text-sm text-slate-600">Registro, pago y revisión de autoafiliaciones.</p></div>
        <form class="flex flex-wrap gap-2">
            <input class="form-input w-64" name="search" value="{{ request('search') }}" placeholder="Código, nombre, CI o transacción">
            <select class="form-input w-48" name="status"><option value="">Todos los estados</option>@foreach(['pending_payment','payment_submitted','under_review','approved','rejected'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select>
            <button class="btn-primary">Filtrar</button>
        </form>
    </div>
    <div class="mt-5 overflow-x-auto bg-white shadow-sm">
        <table class="table"><thead><tr><th>Código</th><th>Solicitante</th><th>Sector / plan</th><th>Monto</th><th>Transacción</th><th>Estado</th><th></th></tr></thead>
        <tbody>@forelse($applications as $application)<tr>
            <td class="font-bold">{{ $application->request_code }}</td>
            <td>{{ $application->person->full_name }}<br><span class="text-xs text-slate-500">CI {{ $application->person->ci }}</span></td>
            <td>{{ $application->sector->name }}<br><span class="text-xs text-slate-500">{{ $application->plan->name }}</span></td>
            <td>BOB {{ number_format($application->amount_due, 2) }}</td>
            <td>{{ $application->payment?->transaction_number ?: 'Sin pago' }}</td>
            <td><span class="badge">{{ str_replace('_',' ',$application->status) }}</span></td>
            <td><a class="btn-secondary" href="{{ route('public-affiliation.admin.show', $application) }}">Revisar</a></td>
        </tr>@empty<tr><td colspan="7">No hay solicitudes con esos filtros.</td></tr>@endforelse</tbody></table>
    </div>
    <div class="mt-5">{{ $applications->links() }}</div>
</x-layouts.app>
