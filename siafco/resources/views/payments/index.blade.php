<x-layouts.app title="Pagos de afiliacion">
    <form class="mb-4 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row">
        <select class="form-input sm:max-w-xs" name="status">
            <option value="">Todos</option>
            @foreach(['pendiente','confirmado','rechazado'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\AffiliationStatusPresenter::label($status) }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Filtrar</button>
    </form>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Afiliado</th><th>Registro</th><th>Monto</th><th>QR pago</th><th>Transaccion</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                @foreach($payments as $payment)
                    <tr>
                        <td>{{ $payment->affiliate->full_name }}</td>
                        <td>{{ $payment->affiliate->registration_number }}</td>
                        <td>Bs {{ number_format($payment->amount, 2) }}</td>
                        <td>
                            @if($payment->status === 'pendiente' && $institution->paymentQrUrl())
                                <img class="h-16 w-16 rounded border object-contain" src="{{ $institution->paymentQrUrl() }}" alt="QR pago">
                            @endif
                        </td>
                        <td>{{ $payment->transaction_number ?: 'Sin registrar' }}</td>
                        <td><x-affiliation-status :status="$payment->status" size="sm" /></td>
                        <td>
                            <a class="btn-secondary" href="{{ route('affiliates.show', $payment->affiliate) }}">Ver</a>
                            @if(auth()->user()->hasRole(['administrador','cajero']) && $payment->status !== 'confirmado')
                                <form class="mt-2 inline" method="post" action="{{ route('payments.confirm', $payment) }}">@csrf<button class="btn-primary">Confirmar</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
