<x-layouts.app title="Pagos de afiliacion">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Pagos de afiliacion</h2>
            <p class="text-sm text-slate-600">Registro, revision y trazabilidad de pagos administrativos y moviles.</p>
        </div>
        @if(auth()->user()->hasPermission('payments.create'))
            <a class="btn-primary" href="{{ route('payments.create') }}">Registrar pago</a>
        @endif
    </div>

    <form class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Nombre, CI, codigo o referencia">
        <select class="form-input" name="status">
            <option value="">Todos los estados</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\PaymentStatus::label($status) }}</option>
            @endforeach
        </select>
        <select class="form-input" name="payment_method">
            <option value="">Todos los metodos</option>
            @foreach(['efectivo' => 'Efectivo', 'qr' => 'QR', 'transferencia' => 'Transferencia', 'deposito' => 'Deposito', 'pos' => 'POS', 'otro' => 'Otro'] as $value => $label)
                <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="form-input" name="source">
            <option value="">Todos los origenes</option>
            @foreach(['web' => 'Web', 'mobile' => 'Movil', 'manual_admin' => 'Manual administrativo'] as $value => $label)
                <option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input class="form-input" type="date" name="date_from" value="{{ request('date_from') }}">
        <input class="form-input" type="date" name="date_to" value="{{ request('date_to') }}">
        <select class="form-input" name="registered_by">
            <option value="">Registrador</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((string) request('registered_by') === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Filtrar</button>
    </form>

    <div class="mobile-card-list">
        @forelse($payments as $payment)
            <article class="mobile-list-card">
                <h2 class="mobile-list-card__title">{{ $payment->affiliate?->full_name ?? 'Afiliado no disponible' }}</h2>
                <p class="mobile-list-card__meta">{{ $payment->affiliate?->registration_number ?: $payment->affiliate?->ci }} · {{ $payment->source ?: 'web' }}</p>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-slate-500">Monto</span><strong class="block">{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</strong></div>
                    <div><span class="text-slate-500">Metodo</span><strong class="block">{{ ucfirst((string) $payment->payment_method) }}</strong></div>
                    <div class="col-span-2"><x-affiliation-status :status="$payment->status" size="sm" /></div>
                </div>
                <a class="btn-secondary mt-4 min-h-12 w-full" href="{{ route('payments.show', $payment) }}">Ver</a>
            </article>
        @empty
            <p class="mobile-list-card text-slate-600">Sin pagos registrados.</p>
        @endforelse
    </div>

    <div class="desktop-table overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Afiliado</th><th>Monto</th><th>Metodo</th><th>Referencia</th><th>Origen</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>
                            <div class="font-bold">{{ $payment->affiliate?->full_name ?? 'Afiliado no disponible' }}</div>
                            <div class="text-xs text-slate-500">{{ $payment->affiliate?->registration_number ?: $payment->affiliate?->ci }}</div>
                        </td>
                        <td>{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</td>
                        <td>{{ ucfirst((string) $payment->payment_method) }}</td>
                        <td>{{ $payment->reference_number ?: ($payment->transaction_number ?: 'Sin referencia') }}</td>
                        <td>{{ $payment->source ?: 'web' }}</td>
                        <td><x-affiliation-status :status="$payment->status" size="sm" /></td>
                        <td class="min-w-64">
                            <div class="flex flex-wrap gap-2">
                                <a class="btn-secondary" href="{{ route('payments.show', $payment) }}">Ver</a>
                                @if(auth()->user()->hasPermission('payments.update_pending') && \App\Support\PaymentStatus::isEditable($payment->status))
                                    <a class="btn-secondary" href="{{ route('payments.edit', $payment) }}">Editar</a>
                                @endif
                                @if(auth()->user()->hasPermission('payments.confirm') && \App\Support\PaymentStatus::isEditable($payment->status))
                                    <form method="post" action="{{ route('payments.confirm', $payment) }}">@csrf<button class="btn-primary">Confirmar</button></form>
                                @endif
                                @if(auth()->user()->hasPermission('payments.view_receipt') && $payment->voucher_path)
                                    <a class="btn-secondary" href="{{ route('payments.voucher', $payment) }}" target="_blank">Comprobante</a>
                                @endif
                                @if(auth()->user()->hasPermission('payments.download_receipt'))
                                    <a class="btn-secondary" href="{{ route('payments.receipt.download', $payment) }}">Recibo</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Sin pagos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
