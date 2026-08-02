<x-layouts.app title="Pedido {{ $order->code }}">
    <section class="section-card">
        <p class="text-sm font-bold uppercase text-[#b8942f]">{{ $order->status }}</p>
        <h2 class="text-2xl font-black text-[#0b1f3a]">{{ $order->code }}</h2>
        <p class="mt-2">Total: <strong>Bs {{ number_format((float) $order->total, 2) }}</strong></p>
        <p class="text-sm text-slate-600">Entrega: {{ $order->delivery_method }}</p>
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
        <p class="text-sm text-slate-600">Realiza el pago por el importe exacto e incluye el código {{ $order->code }} como referencia. La carga de comprobantes se habilita en esta pantalla.</p>
        <p class="mt-2">Subtotal Bs {{ number_format((float) $order->subtotal, 2) }} · Descuento Bs {{ number_format((float) $order->discount_total, 2) }} · Envío Bs {{ number_format((float) $order->shipping_total, 2) }}</p>
    </section>
    <section class="section-card mt-5">
        <h3 class="mb-3 text-lg font-black text-[#0b1f3a]">Historial</h3>
        @forelse($order->statusHistories as $history)
            <p class="text-sm">{{ $history->from_status ?: 'inicio' }} → <strong>{{ $history->to_status }}</strong> · {{ $history->changed_at->format('d/m/Y H:i') }}</p>
        @empty
            <p class="text-sm text-slate-600">Sin cambios registrados.</p>
        @endforelse
    </section>
</x-layouts.app>
