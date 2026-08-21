<x-layouts.app title="Planes de afiliacion">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <form method="get" class="flex items-end gap-2">
            <label><span class="form-label">Sector</span><select class="form-input" name="sector_id"><option value="">Todos los sectores</option>@foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected((string)($filters['sector_id'] ?? '') === (string)$sector->id)>{{ $sector->name }}</option>@endforeach</select></label>
            <button class="btn-secondary">Filtrar</button>
        </form>
        <a class="btn-primary" href="{{ route('plans.create') }}">Nuevo plan</a>
    </div>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Plan</th><th>Sector</th><th>Afiliacion</th><th>Credencial</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                @foreach($plans as $plan)
                    <tr>
                        <td>{{ $plan->name }}</td>
                        <td>{{ $plan->sector?->name ?? 'General / Sin sector' }}</td>
                        <td>Bs {{ number_format($plan->affiliation_fee, 2) }}</td>
                        <td>Bs {{ number_format($plan->credential_fee, 2) }}</td>
                        <td class="font-black">Bs {{ number_format($plan->total_amount, 2) }}</td>
                        <td><span class="badge">{{ $plan->is_active ? 'activo' : 'inactivo' }}</span></td>
                        <td class="text-right"><a class="btn-secondary" href="{{ route('plans.edit', $plan) }}">Editar</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $plans->links() }}</div>
</x-layouts.app>
