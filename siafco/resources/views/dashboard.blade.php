<x-layouts.app title="Dashboard">
    <section class="mb-6 flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 sm:flex-row sm:items-center">
        <div class="grid h-16 w-16 place-items-center overflow-hidden rounded border border-[#d4af37] bg-slate-50 text-xl font-black text-[#0b1f3a]">
            @if($institution->logoUrl())
                <img class="h-full w-full object-contain p-2" src="{{ $institution->logoUrl() }}" alt="Logo">
            @else
                SIAFCO
            @endif
        </div>
        <div>
            <p class="text-sm font-black uppercase text-[#b8942f]">{{ $institution->institution_name }}</p>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Sistema Integral de Afiliacion Cooperativa Tierra Bendita</h2>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach([
            'Afiliados' => $metrics['affiliates'],
            'Activos' => $metrics['active'],
            'Pendientes' => $metrics['pendingPayments'],
            'Confirmados' => $metrics['confirmedPayments'],
            'Credenciales' => $metrics['credentials'],
            'Sectores' => $metrics['sectors'],
        ] as $label => $value)
            <article class="metric-card">
                <p>{{ $label }}</p>
                <strong>{{ $value }}</strong>
            </article>
        @endforeach
    </div>

    <section class="mt-8 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="font-black">Pagos recientes</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Afiliado</th><th>Monto</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                @forelse($recentPayments as $payment)
                    <tr>
                        <td>{{ $payment->affiliate->full_name }}</td>
                        <td>Bs {{ number_format($payment->amount, 2) }}</td>
                        <td><x-affiliation-status :status="$payment->status" size="sm" /></td>
                        <td>{{ $payment->created_at->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Sin pagos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
