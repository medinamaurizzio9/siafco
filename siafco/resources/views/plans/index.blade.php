<x-layouts.app title="Planes de afiliacion">
    <div class="mb-4 flex justify-end"><a class="btn-primary" href="{{ route('plans.create') }}">Nuevo plan</a></div>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Plan</th><th>Afiliacion</th><th>Credencial</th><th>Total</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach($plans as $plan)
                    <tr>
                        <td>{{ $plan->name }}</td>
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
