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
                <p class="mt-2 rounded bg-[#fff8df] p-3 text-sm">Cupón aplicado: {{ $order->coupon_snapshot['code_hint'] ?? 'Registrado' }} - descuento Bs {{ number_format((float) $order->discount_total, 2) }}</p>
            @endif
        </section>

        <section class="section-card">
            <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Totales</h3>
            <div class="grid gap-2 text-sm">
                <p>Subtotal: <strong>Bs {{ number_format((float) $order->subtotal, 2) }}</strong></p>
                <p>Descuento: <strong>Bs {{ number_format((float) $order->discount_total, 2) }}</strong></p>
                <p>Envío: <strong>Bs {{ number_format((float) $order->shipping_total, 2) }}</strong></p>
                <p class="text-lg">Total: <strong>Bs {{ number_format((float) $order->total, 2) }}</strong></p>
            </div>
        </section>
    </div>

    <section class="section-card mt-5">
        <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Items</h3>
        <div class="overflow-x-auto">
            <table class="table min-w-full">
                <thead><tr><th>SKU</th><th>Producto</th><th>Variante</th><th>Cantidad</th><th>Unitario</th><th>Descuento</th><th>Total línea</th></tr></thead>
                <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->sku_snapshot }}</td>
                        <td>{{ $item->name_snapshot }}</td>
                        <td>{{ $item->variant_snapshot ?: '-' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>Bs {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>Bs {{ number_format((float) $item->discount_total, 2) }}</td>
                        <td>Bs {{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
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
                    <select class="form-input" name="status" required>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected($order->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <textarea class="form-input" name="admin_note" rows="3" placeholder="Observación administrativa opcional"></textarea>
                    <button class="btn-primary">Actualizar estado</button>
                </form>
            @else
                <p class="text-sm text-slate-600">Solo lectura.</p>
            @endcan
        </div>

        <div class="section-card">
            <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Historial</h3>
            <div class="grid gap-2 text-sm">
                @forelse($order->statusHistories as $history)
                    <p class="rounded bg-slate-50 p-3">{{ $history->from_status ?: 'inicio' }} → <strong>{{ $history->to_status }}</strong><br><span class="text-slate-500">{{ $history->changed_at->format('d/m/Y H:i') }} · {{ $history->actor?->name ?: 'Sistema' }}</span></p>
                @empty
                    <p class="text-slate-600">Sin historial registrado.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="section-card mt-5">
        <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Comprobante</h3>
        <p class="text-sm text-slate-600">{{ $order->receipts->isEmpty() ? 'La carga y revisión de comprobantes se implementará en una fase posterior.' : 'Este pedido tiene comprobantes registrados.' }}</p>
    </section>
</x-layouts.app>
