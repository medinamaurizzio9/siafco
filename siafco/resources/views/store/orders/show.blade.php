<x-layouts.app title="Pedido {{ $order->code }}">
    <div class="mb-4">
        <a class="btn-secondary" href="{{ route('store.orders.index') }}">Volver a Mis pedidos</a>
    </div>

    <section class="section-card">
        <p class="text-sm font-bold uppercase text-[#b8942f]">{{ $order->status }}</p>
        <h2 class="text-2xl font-black text-[#0b1f3a]">{{ $order->code }}</h2>
        <p class="mt-2">Total: <strong>Bs {{ number_format((float) $order->total, 2) }}</strong></p>
        <p class="text-sm text-slate-600">Entrega: {{ $order->delivery_method }}</p>
        <form class="mt-4" method="post" action="{{ route('store.orders.whatsapp', $order) }}" target="_blank" rel="noopener">
            @csrf
            <button class="btn-secondary">Coordinar por WhatsApp</button>
        </form>
        <p class="mt-2 text-xs text-slate-500">Si WhatsApp no se abre, permite ventanas emergentes para este sitio.</p>
    </section>

    <section class="mt-5 grid gap-3">
        @foreach($order->items as $item)
            <article class="rounded bg-white p-4 shadow">
                <h3 class="font-black text-[#0b1f3a]">{{ $item->name_snapshot }}</h3>
                <p class="text-sm text-slate-600">{{ $item->variant_snapshot ?: 'Sin variante' }} · {{ $item->quantity }} unidades</p>
                <p>Bs {{ number_format((float) $item->line_total, 2) }}</p>
            </article>
        @endforeach
    </section>

    <section class="section-card mt-5">
        <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Pago</h3>
        <p class="text-sm text-slate-600">Realiza el pago por el importe exacto e incluye el codigo {{ $order->code }} como referencia.</p>
        <p class="mt-2">Subtotal Bs {{ number_format((float) $order->subtotal, 2) }} · Descuento Bs {{ number_format((float) $order->discount_total, 2) }} · Envio Bs {{ number_format((float) $order->shipping_total, 2) }}</p>
        @if(in_array($order->status, ['pendiente', 'esperando_pago'], true) && ! $order->receipts->where('status', 'pending')->count())
            <form class="mt-4 grid gap-3" method="post" action="{{ route('store.orders.receipts.store', $order) }}" enctype="multipart/form-data">
                @csrf
                <input class="form-input" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                <button class="btn-primary">Enviar comprobante</button>
            </form>
        @endif
        <div class="mt-4 grid gap-2">
            @foreach($order->receipts as $receipt)
                <p class="rounded bg-slate-50 p-3 text-sm">Comprobante {{ $receipt->status }} · {{ $receipt->submitted_at->format('d/m/Y H:i') }} · <a class="font-bold text-[#0b1f3a]" href="{{ route('store.orders.receipts.show', [$order, $receipt]) }}">Descargar</a></p>
            @endforeach
        </div>
    </section>

    <section class="section-card mt-5">
        <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Historial</h3>
        @forelse($order->statusHistories as $history)
            <p class="text-sm">{{ $history->from_status ?: 'inicio' }} -> <strong>{{ $history->to_status }}</strong> · {{ $history->changed_at->format('d/m/Y H:i') }}</p>
        @empty
            <p class="text-sm text-slate-600">Sin cambios registrados.</p>
        @endforelse
    </section>
</x-layouts.app>
