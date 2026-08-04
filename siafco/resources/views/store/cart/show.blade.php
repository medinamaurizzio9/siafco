<x-layouts.app title="Mi carrito">
    <div class="mb-4">
        <a class="btn-secondary" href="{{ route('store.catalog.index') }}">Seguir comprando</a>
    </div>

    @if(! $quote)
        <div class="rounded bg-white p-5 text-slate-600 shadow">Tu carrito está vacío.</div>
    @else
        <section class="grid gap-4">
            @foreach($quote['lines'] as $index => $line)
                @php($cartLine = $cart->lines()[$index])
                <article class="rounded bg-white p-4 shadow">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="font-black text-[#0b1f3a]">{{ $line['product']->name }}</h3>
                            <p class="text-sm text-slate-600">{{ $line['variant'] ? $line['variant']->type.' '.$line['variant']->name : 'Sin variante' }}</p>
                            <p class="text-sm">Unitario Bs {{ $line['unit_price'] }} · Línea Bs {{ $line['line_total'] }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="post" action="{{ route('store.cart.update', $cartLine['line_key']) }}" class="flex gap-2">
                                @csrf @method('patch')
                                <input class="form-input w-24" type="number" name="quantity" min="1" max="{{ $line['product']->max_quantity_per_order }}" value="{{ $line['quantity'] }}">
                                <button class="btn-secondary">Actualizar</button>
                            </form>
                            <form method="post" action="{{ route('store.cart.destroy', $cartLine['line_key']) }}">
                                @csrf @method('delete')
                                <button class="btn-danger">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="mt-5 rounded bg-white p-5 shadow">
            <p>Subtotal: <strong>Bs {{ $quote['subtotal'] }}</strong></p>
            <p>Total estimado con recojo: <strong>Bs {{ $quote['total'] }}</strong></p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a class="btn-primary" href="{{ route('store.catalog.index') }}">Seguir comprando</a>
                <a class="btn-primary" href="{{ route('store.checkout.show') }}">Ir a checkout</a>
                <form method="post" action="{{ route('store.cart.clear') }}">@csrf @method('delete')<button class="btn-secondary">Vaciar carrito</button></form>
            </div>
        </section>
    @endif
</x-layouts.app>
