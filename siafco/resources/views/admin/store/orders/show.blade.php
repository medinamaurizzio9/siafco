<x-layouts.app title="Pedido {{ $order->code }}">
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="section-card lg:col-span-2">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-bold uppercase text-[#b8942f]">{{ $order->status }}</p>
                    <h2 class="text-2xl font-black text-[#0b1f3a]">{{ $order->code }}</h2>
                </div>
                <a class="btn-secondary" href="{{ route('admin.store.orders.index') }}">Volver</a>
            </div>
            <p class="text-sm text-slate-600">Afiliado: <strong>{{ $order->affiliate?->full_name }}</strong></p>
            <p class="text-sm text-slate-600">Entrega: <strong>{{ $order->delivery_method }}</strong></p>
            @if($order->coupon_snapshot)
                <p class="mt-2 rounded bg-[#fff8df] p-3 text-sm">Cupon aplicado: {{ $order->coupon_snapshot['code_hint'] ?? 'Registrado' }} - descuento Bs {{ number_format((float) $order->discount_total, 2) }}</p>
            @endif
        </section>
        <section class="section-card">
            <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Totales</h3>
            <p>Subtotal: <strong>Bs {{ number_format((float) $order->subtotal, 2) }}</strong></p>
            <p>Descuento: <strong>Bs {{ number_format((float) $order->discount_total, 2) }}</strong></p>
            <p>Envio: <strong>Bs {{ number_format((float) $order->shipping_total, 2) }}</strong></p>
            <p class="text-lg">Total: <strong>Bs {{ number_format((float) $order->total, 2) }}</strong></p>
        </section>
    </div>

    <section class="section-card mt-5">
        <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Items</h3>
        <div class="overflow-x-auto">
            <table class="table min-w-full">
                <thead><tr><th>SKU</th><th>Producto</th><th>Variante</th><th>Cantidad</th><th>Unitario</th><th>Descuento</th><th>Total linea</th></tr></thead>
                <tbody>
                @foreach($order->items as $item)
                    <tr><td>{{ $item->sku_snapshot }}</td><td>{{ $item->name_snapshot }}</td><td>{{ $item->variant_snapshot ?: '-' }}</td><td>{{ $item->quantity }}</td><td>Bs {{ number_format((float) $item->unit_price, 2) }}</td><td>Bs {{ number_format((float) $item->discount_total, 2) }}</td><td>Bs {{ number_format((float) $item->line_total, 2) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="section-card">
            <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Cambiar estado</h3>
            @can('store.manage-orders')
                <form class="grid gap-3" method="post" action="{{ route('admin.store.orders.status', $order) }}">
                    @csrf @method('patch')
                    <select class="form-input" name="status" required>@foreach($statuses as $status)<option value="{{ $status }}" @selected($order->status === $status)>{{ $status }}</option>@endforeach</select>
                    <textarea class="form-input" name="admin_note" rows="3" placeholder="Observacion administrativa opcional"></textarea>
                    <button class="btn-primary">Actualizar estado</button>
                </form>
            @else
                <p class="text-sm text-slate-600">Solo lectura.</p>
            @endcan
        </div>
        <div class="section-card">
            <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Historial</h3>
            <div class="grid gap-2 text-sm">@forelse($order->statusHistories as $history)<p class="rounded bg-slate-50 p-3">{{ $history->from_status ?: 'inicio' }} -> <strong>{{ $history->to_status }}</strong><br><span class="text-slate-500">{{ $history->changed_at->format('d/m/Y H:i') }} · {{ $history->actor?->name ?: 'Sistema' }}</span></p>@empty<p class="text-slate-600">Sin historial registrado.</p>@endforelse</div>
        </div>
    </section>

    <section class="section-card mt-5">
        <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Comprobante</h3>
        <div class="grid gap-3">
            @forelse($order->receipts as $receipt)
                <article class="rounded bg-slate-50 p-3 text-sm">
                    <p><strong>{{ $receipt->status }}</strong> · {{ number_format($receipt->size_bytes / 1024, 1) }} KB · {{ $receipt->mime }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a class="btn-secondary" href="{{ route('admin.store.orders.receipts.show', [$order, $receipt]) }}">Descargar</a>
                        @can('store.verify-receipts')
                            @if($receipt->status === 'pending')
                                <form method="post" action="{{ route('admin.store.orders.receipts.confirm', [$order, $receipt]) }}">@csrf<button class="btn-primary">Confirmar</button></form>
                                <form class="flex gap-2" method="post" action="{{ route('admin.store.orders.receipts.reject', [$order, $receipt]) }}">@csrf<input class="form-input" name="reason" required placeholder="Motivo"><button class="btn-danger">Rechazar</button></form>
                            @endif
                        @endcan
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-600">Sin comprobantes registrados.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
