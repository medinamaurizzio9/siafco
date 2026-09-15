<x-layouts.app title="Todos los Pagos">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Todos los Pagos</h2>
            <p class="text-sm text-slate-600">Consulta y trazabilidad de pagos de afiliación por todos los canales.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(auth()->user()->isInternal() && auth()->user()->hasRole(['superadministrador','administrador','gerente','caja','cajero']))
                <a class="btn-secondary" href="{{ route('admin.collections.index') }}">Reporte de cobros</a>
            @endif
            @if(auth()->user()->hasPermission('payments.create'))
                <a class="btn-primary" href="{{ route('payments.create') }}">Registrar pago</a>
            @endif
        </div>
    </div>

    @if(auth()->user()->hasRole(['superadministrador','administrador','gerente']) && $underReviewCount > 0)
        <a class="mb-4 flex items-center justify-between rounded-lg border border-sky-300 bg-sky-50 p-4 text-sky-950" href="{{ route('payments.index', ['status' => \App\Support\PaymentStatus::UNDER_REVIEW]) }}">
            <span><strong>Pagos pendientes de verificación</strong><span class="ml-2">{{ $underReviewCount }}</span></span>
            <span class="font-bold">Revisar</span>
        </a>
    @endif

    <form class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Nombre, CI, SOL, registro, recibo o transacción">
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
            @foreach(['web' => 'Web', 'mobile' => 'App', 'manual_admin' => 'Oficina', 'office_cash' => 'Efectivo en oficina', 'office_qr' => 'QR en oficina'] as $value => $label)
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
                <p class="mobile-list-card__meta">{{ $payment->affiliate?->registration_number ?: $payment->affiliate?->ci }} · {{ $payment->publicRequest?->request_code ?: 'Sin SOL' }}</p>
                <span class="badge mt-2 {{ \App\Support\PaymentSourcePresenter::badgeClasses($payment->source) }}">{{ \App\Support\PaymentSourcePresenter::channel($payment->source) }}</span>
                <p class="mt-2 text-sm"><span class="text-slate-500">Cobrado por:</span> <strong>{{ $payment->registrar?->name ?? 'Sin registro' }}</strong></p>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-slate-500">Monto</span><strong class="block">{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</strong></div>
                    <div><span class="text-slate-500">Metodo</span><strong class="block">{{ \App\Support\PaymentMethodPresenter::label($payment->payment_method) }}</strong></div>
                    <div><span class="text-slate-500">Recibo</span><strong class="block">{{ $payment->receipt_number ?: 'Sin recibo' }}</strong></div>
                    <div class="col-span-2"><x-payment-status :status="$payment->status" size="sm" /></div>
                </div>
                <div class="mt-4 grid gap-2">
                    <a class="btn-secondary min-h-12 w-full" href="{{ route('payments.show', $payment) }}">Ver</a>
                    @if((auth()->user()->hasPermission('payments.view_receipt') || auth()->user()->hasRole('caja')) && $payment->canRenderReceipt())
                        <a class="btn-secondary min-h-12 w-full" href="{{ route('admin.payments.receipt', $payment) }}" target="_blank">Imprimir recibo</a>
                    @endif
                </div>
            </article>
        @empty
            <p class="mobile-list-card text-slate-600">Sin pagos registrados.</p>
        @endforelse
    </div>

    <div class="desktop-table overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Afiliado</th><th>SOL / recibo</th><th>Monto</th><th>Metodo</th><th>N.º de transacción</th><th>Origen</th><th>Cajero / registrador</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>
                            <div class="font-bold">{{ $payment->affiliate?->full_name ?? 'Afiliado no disponible' }}</div>
                            <div class="text-xs text-slate-500">{{ $payment->affiliate?->registration_number ?: $payment->affiliate?->ci }}</div>
                        </td>
                        <td>
                            <div class="font-bold">{{ $payment->publicRequest?->request_code ?: 'Sin SOL' }}</div>
                            <div class="text-xs text-slate-500">{{ $payment->receipt_number ?: 'Sin recibo' }}</div>
                        </td>
                        <td>{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</td>
                        <td>{{ \App\Support\PaymentMethodPresenter::label($payment->payment_method) }}</td>
                        <td>{{ \App\Support\PaymentMethodPresenter::showsTransactionNumber($payment->payment_method) ? ($payment->reference_number ?: 'No registrado') : 'No aplica' }}</td>
                        <td><span class="badge {{ \App\Support\PaymentSourcePresenter::badgeClasses($payment->source) }}">{{ \App\Support\PaymentSourcePresenter::channel($payment->source) }}</span><div class="mt-1 text-xs text-slate-500">{{ \App\Support\PaymentSourcePresenter::label($payment->source) }}</div></td>
                        <td>{{ $payment->registrar?->name ?? 'Sin registro' }}</td>
                        <td><x-payment-status :status="$payment->status" size="sm" /></td>
                        <td class="min-w-64">
                            <div class="flex flex-wrap gap-2">
                                <a class="btn-secondary" href="{{ route('payments.show', $payment) }}">Ver</a>
                                @if(auth()->user()->hasPermission('payments.update_pending') && \App\Support\PaymentStatus::isEditable($payment->status))
                                    <a class="btn-secondary" href="{{ route('payments.edit', $payment) }}">Editar</a>
                                @endif
                                @if(app(\App\Services\PaymentActionAuthorization::class)->canConfirm(auth()->user(), $payment))
                                    <form method="post" action="{{ route('payments.confirm', $payment) }}" data-confirm-title="{{ $payment->source === 'office_qr' ? 'Confirmar pago QR' : 'Confirmar pago' }}" data-confirm-message="{{ $payment->source === 'office_qr' ? 'Confirme que la operación '.$payment->reference_number.' por '.($payment->currency ?? 'BOB').' '.number_format((float) ($payment->paid_amount ?? $payment->amount), 2).' fue verificada correctamente.' : 'El pago quedará confirmado.' }}" data-confirm-accept="Confirmar pago" data-confirm-variant="warning">@csrf<button class="btn-primary">Confirmar</button></form>
                                @endif
                                @if(auth()->user()->hasPermission('payments.view_receipt') && $payment->voucher_path)
                                    <a class="btn-secondary" href="{{ route('payments.voucher', $payment) }}" target="_blank">Comprobante</a>
                                @endif
                                @if((auth()->user()->hasPermission('payments.view_receipt') || auth()->user()->hasRole('caja')) && $payment->canRenderReceipt())
                                    <a class="btn-secondary" href="{{ route('admin.payments.receipt', $payment) }}" target="_blank">Imprimir recibo</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">Sin pagos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
