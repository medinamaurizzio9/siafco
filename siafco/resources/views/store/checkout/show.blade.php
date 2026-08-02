<x-layouts.app title="Checkout">
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="section-card lg:col-span-2">
            <h2 class="mb-4 text-xl font-black text-[#0b1f3a]">Entrega y cupón</h2>
            <form class="grid gap-4" method="post" action="{{ route('store.orders.store') }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="rounded border border-slate-200 p-3"><input type="radio" name="delivery_method" value="pickup" @checked(($delivery['method'] ?? 'pickup') === 'pickup')> Recojo en oficina</label>
                    <label class="rounded border border-slate-200 p-3"><input type="radio" name="delivery_method" value="shipping" @checked(($delivery['method'] ?? '') === 'shipping')> Envío nacional</label>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <input class="form-input" name="department" value="{{ old('department', $delivery['department'] ?? '') }}" placeholder="Departamento">
                    <input class="form-input" name="city" value="{{ old('city', $delivery['city'] ?? '') }}" placeholder="Ciudad">
                    <input class="form-input" name="zone" value="{{ old('zone', $delivery['zone'] ?? '') }}" placeholder="Zona">
                    <input class="form-input" name="coupon_code" value="{{ old('coupon_code', $couponCode) }}" placeholder="Cupón">
                </div>
                <textarea class="form-input" name="delivery_address" rows="3" placeholder="Dirección y referencia">{{ old('delivery_address', $delivery['address'] ?? '') }}</textarea>
                <button class="btn-primary">Crear pedido seguro</button>
            </form>
        </section>
        <aside class="section-card">
            <h2 class="mb-4 text-xl font-black text-[#0b1f3a]">Resumen</h2>
            <div class="grid gap-3">
                @foreach($quote['lines'] as $line)
                    <p class="text-sm">{{ $line['quantity'] }} x {{ $line['product']->name }}<br><strong>Bs {{ $line['line_total'] }}</strong></p>
                @endforeach
                <hr>
                <p>Subtotal: <strong>Bs {{ $quote['subtotal'] }}</strong></p>
                <p>Descuento: <strong>Bs {{ $quote['discount_total'] }}</strong></p>
                <p>Envío: <strong>Bs {{ $quote['shipping_total'] }}</strong></p>
                <p class="text-lg">Total: <strong>Bs {{ $quote['total'] }}</strong></p>
            </div>
        </aside>
    </div>
</x-layouts.app>
