<x-layouts.app title="Reporte de cobros">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Reporte de cobros</h2>
            <p class="text-sm text-slate-600">Consulta de pagos confirmados por fecha, cajero y afiliado.</p>
        </div>
        <a class="btn-secondary" href="{{ route('payments.index') }}">Pagos de afiliacion</a>
    </div>

    <form class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-3 xl:grid-cols-4" method="get">
        <div>
            <label class="form-label">Fecha desde</label>
            <input class="form-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div>
            <label class="form-label">Fecha hasta</label>
            <input class="form-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div>
            <label class="form-label">Cajero</label>
            <select class="form-input" name="cashier_id" @disabled(! $canFilterAnyCashier)>
                <option value="">Todos los cajeros</option>
                @foreach($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected((string) ($filters['cashier_id'] ?? '') === (string) $cashier->id)>{{ $cashier->name }}</option>
                @endforeach
            </select>
            @unless($canFilterAnyCashier)
                <input type="hidden" name="cashier_id" value="{{ auth()->id() }}">
                <p class="mt-1 text-xs text-slate-500">Solo puede consultar sus propios cobros.</p>
            @endunless
        </div>
        <div>
            <label class="form-label">N. recibo</label>
            <input class="form-input" name="receipt_number" value="{{ $filters['receipt_number'] ?? '' }}">
        </div>
        <div>
            <label class="form-label">N.º transferencia / operación</label>
            <input class="form-input" name="reference_number" value="{{ $filters['reference_number'] ?? '' }}">
        </div>
        <div>
            <label class="form-label">CI</label>
            <input class="form-input" name="ci" value="{{ $filters['ci'] ?? '' }}">
        </div>
        <div>
            <label class="form-label">Nombre afiliado</label>
            <input class="form-input" name="affiliate_name" value="{{ $filters['affiliate_name'] ?? '' }}">
        </div>
        <div>
            <label class="form-label">Sector</label>
            <select class="form-input" name="sector_id">
                <option value="">Todos</option>
                @foreach($sectors as $sector)
                    <option value="{{ $sector->id }}" @selected((string) ($filters['sector_id'] ?? '') === (string) $sector->id)>{{ $sector->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Plan</label>
            <select class="form-input" name="affiliation_plan_id">
                <option value="">Todos</option>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected((string) ($filters['affiliation_plan_id'] ?? '') === (string) $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Metodo</label>
            <select class="form-input" name="payment_method">
                <option value="">Todos</option>
                @foreach(['efectivo' => 'Efectivo', 'qr' => 'QR', 'transferencia' => 'Transferencia', 'deposito' => 'Deposito', 'pos' => 'POS', 'otro' => 'Otro'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['payment_method'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Estado</label>
            <select class="form-input" name="status">
                <option value="">Confirmados</option>
                @foreach(\App\Support\PaymentStatus::confirmedValues() as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\PaymentStatus::label($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2 xl:col-span-2">
            <button class="btn-secondary">Filtrar</button>
            <a class="btn-secondary" href="{{ route('admin.collections.index') }}">Limpiar</a>
        </div>
    </form>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="metric-card"><p>Cantidad de cobros confirmados</p><strong>{{ $collectionCount }}</strong></article>
        <article class="metric-card"><p>Total confirmado</p><strong>BOB {{ number_format($totalAmount, 2) }}</strong></article>
        <article class="metric-card"><p>QR pendientes</p><strong>{{ $pendingQrCount }}</strong></article>
        <article class="metric-card"><p>Monto pendiente</p><strong>BOB {{ number_format($pendingQrAmount, 2) }}</strong></article>
        @foreach($totalsByMethod as $method => $amount)
            <article class="metric-card"><p>Total {{ ucfirst(str_replace('_', ' ', $method)) }}</p><strong>BOB {{ number_format((float) $amount, 2) }}</strong></article>
        @endforeach
    </div>

    @if($pendingPayments->isNotEmpty())
        <section class="mb-5 overflow-hidden rounded-lg border border-amber-300 bg-white">
            <div class="border-b border-amber-200 bg-amber-50 p-4"><h3 class="font-black text-amber-950">Pagos QR pendientes de verificación</h3></div>
            <div class="overflow-x-auto"><table class="table"><thead><tr><th>Fecha</th><th>Afiliado</th><th>CI</th><th>Monto</th><th>Operación</th><th>Registrado por</th><th>Acción</th></tr></thead><tbody>
            @foreach($pendingPayments as $pending)
                <tr><td>{{ $pending->paid_at?->format('d/m/Y H:i') }}</td><td>{{ $pending->affiliate?->full_name }}</td><td>{{ $pending->affiliate?->ci }}</td><td>{{ $pending->currency ?? 'BOB' }} {{ number_format((float) ($pending->paid_amount ?? $pending->amount), 2) }}</td><td>{{ $pending->reference_number }}</td><td>{{ $pending->registrar?->name ?? 'No registrado' }}</td><td><a class="btn-secondary" href="{{ route('payments.show', $pending) }}">Ver</a></td></tr>
            @endforeach
            </tbody></table></div>
        </section>
    @endif

    <div class="desktop-table overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>N. recibo</th>
                        <th>Fecha y hora</th>
                        <th>Afiliado</th>
                        <th>CI</th>
                        <th>Codigo</th>
                        <th>Sector</th>
                        <th>Plan</th>
                        <th>Monto</th>
                        <th>Metodo</th>
                        <th>Estado</th>
                        <th>Cajero</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td class="font-black">{{ $payment->receipt_number ?: 'Sin recibo' }}</td>
                        <td>{{ $payment->confirmed_at?->format('d/m/Y H:i') ?? $payment->paid_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $payment->affiliate?->full_name ?? 'Afiliado no disponible' }}</td>
                        <td>{{ $payment->affiliate?->ci ?? 'No disponible' }}</td>
                        <td>{{ $payment->affiliate?->registration_number ?: 'Sin codigo' }}</td>
                        <td>{{ $payment->affiliate?->sector?->name ?? 'Sin sector' }}</td>
                        <td>{{ $payment->affiliate?->plan?->name ?? 'Sin plan' }}</td>
                        <td>{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</td>
                        <td>{{ $payment->payment_method === 'efectivo' && $payment->source === 'office_cash' ? 'Efectivo / Oficina' : ucfirst((string) $payment->payment_method) }}</td>
                        <td><x-payment-status :status="$payment->status" size="sm" /></td>
                        <td>{{ $payment->cashier?->name ?? 'No registrado' }}</td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <a class="btn-secondary px-3 py-2 text-xs" href="{{ route('admin.payments.receipt', $payment) }}" target="_blank">Ver recibo</a>
                                <a class="btn-secondary px-3 py-2 text-xs" href="{{ route('admin.payments.receipt', $payment) }}" target="_blank">Imprimir</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12">Sin cobros confirmados para los filtros seleccionados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
