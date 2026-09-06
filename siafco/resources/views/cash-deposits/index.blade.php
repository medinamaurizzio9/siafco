<x-layouts.app title="Rendiciones de caja">
    <div class="space-y-5">
        <header><h2 class="text-3xl font-black text-[#0b1f3a]">Rendiciones de caja</h2><p class="text-slate-600">Depósitos registrados por Caja pendientes de conciliación.</p></header>
        <section class="grid gap-3 sm:grid-cols-2"><article class="section-card"><p class="text-sm font-black uppercase text-slate-500">Rendiciones pendientes</p><strong class="text-3xl text-[#0b1f3a]">{{ $pendingCount }}</strong></article><article class="section-card"><p class="text-sm font-black uppercase text-slate-500">Monto pendiente de revisión</p><strong class="text-3xl text-[#0b1f3a]">BOB {{ number_format($pendingAmount, 2) }}</strong></article></section>
        <form class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-5"><select class="form-input" name="cashier_id"><option value="">Todos los cajeros</option>@foreach($cashiers as $cashier)<option value="{{ $cashier->id }}" @selected((string)($filters['cashier_id'] ?? '') === (string)$cashier->id)>{{ $cashier->name }}</option>@endforeach</select><select class="form-input" name="status"><option value="">Todos los estados</option>@foreach(['under_review'=>'En revisión','confirmed'=>'Confirmado','rejected'=>'Rechazado'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select><input class="form-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"><input class="form-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"><input class="form-input" name="transaction_number" placeholder="N.º transacción" value="{{ $filters['transaction_number'] ?? '' }}"><button class="btn-secondary md:col-span-5">Filtrar</button></form>
        <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="table">
                <thead><tr><th>Cajero</th><th>Monto</th><th>Fecha</th><th>N.º transacción</th><th>Comprobante</th><th>Estado</th><th>Registrado</th><th>Revisado por</th><th>Acciones</th></tr></thead>
                <tbody>
                @forelse($deposits as $deposit)
                    <tr>
                        <td>{{ $deposit->registrar?->name }}</td>
                        <td>{{ $deposit->currency }} {{ number_format((float) $deposit->amount, 2) }}</td>
                        <td>{{ $deposit->deposited_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $deposit->transaction_number }}</td>
                        <td>@if($deposit->voucher_path)<a class="font-bold text-blue-700 underline" target="_blank" href="{{ route('cash-deposits.admin.voucher', $deposit) }}">Ver</a>@else No adjunto @endif</td>
                        <td>{{ $deposit->status === 'confirmed' ? 'Confirmado' : ($deposit->status === 'rejected' ? 'Rechazado' : 'En revisión') }}</td>
                        <td>{{ $deposit->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $deposit->reviewer?->name ?? 'Pendiente' }}</td>
                        <td>
                            @if($deposit->status === 'under_review')
                                <div class="flex gap-2">
                                    <a class="btn-secondary" href="{{ route('cash-deposits.admin.show', $deposit) }}">Ver</a>
                                    <form method="post" action="{{ route('cash-deposits.admin.confirm', $deposit) }}" data-confirm-title="¿Confirmar depósito de caja?" data-confirm-message="Cajero: {{ $deposit->registrar?->name }}. Monto: {{ $deposit->currency }} {{ number_format((float) $deposit->amount, 2) }}. Transacción: {{ $deposit->transaction_number }}. Fecha: {{ $deposit->deposited_at->format('d/m/Y') }}." data-confirm-accept="Confirmar">@csrf<button class="btn-primary">Confirmar</button></form>
                                    <form method="post" action="{{ route('cash-deposits.admin.reject', $deposit) }}" data-confirm-title="Rechazar depósito" data-confirm-message="El depósito quedará rechazado con el motivo indicado." data-confirm-accept="Rechazar" data-confirm-variant="danger">@csrf<input class="form-input" name="rejection_reason" placeholder="Motivo" required minlength="5"><button class="btn-danger mt-2">Rechazar</button></form>
                                </div>
                            @elseif($deposit->rejection_reason)
                                <a class="btn-secondary" href="{{ route('cash-deposits.admin.show', $deposit) }}">Ver</a><span class="ml-2 text-sm text-red-700">{{ $deposit->rejection_reason }}</span>
                            @else
                                <a class="btn-secondary" href="{{ route('cash-deposits.admin.show', $deposit) }}">Ver</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">Sin rendiciones registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
        {{ $deposits->links() }}
    </div>
</x-layouts.app>
