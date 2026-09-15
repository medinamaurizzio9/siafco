<x-layouts.app title="Mi carrito">
    <div class="store-pwa-shell">
        <a class="store-pwa-back" href="{{ route('store.catalog.index') }}">
            <x-ui.icon name="arrow-left" class="h-5 w-5" />
            <span>Seguir comprando</span>
        </a>

        @if(! $quote)
            <div class="store-pwa-empty">Tu carrito está vacío.</div>
        @else
            <section class="store-pwa-list">
                @foreach($quote['lines'] as $index => $line)
                    @php($cartLine = $cart->lines()[$index])
                    <article class="store-pwa-line">
                        <div>
                            <h3>{{ $line['product']->name }}</h3>
                            <p>{{ $line['variant'] ? $line['variant']->type.' '.$line['variant']->name : 'Sin variante' }}</p>
                            <strong>Bs {{ $line['line_total'] }}</strong>
                            <span>Unitario Bs {{ $line['unit_price'] }}</span>
                        </div>
                        <div class="store-pwa-line__actions">
                            <form method="post" action="{{ route('store.cart.update', $cartLine['line_key']) }}">
                                @csrf @method('patch')
                                <input class="form-input" type="number" name="quantity" min="1" max="{{ $line['product']->max_quantity_per_order }}" value="{{ $line['quantity'] }}">
                                <button class="btn-secondary">Actualizar</button>
                            </form>
                            <form method="post" action="{{ route('store.cart.destroy', $cartLine['line_key']) }}">
                                @csrf @method('delete')
                                <button class="btn-danger">Eliminar</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="store-pwa-summary">
                <div>
                    <span>Subtotal</span>
                    <strong>Bs {{ $quote['subtotal'] }}</strong>
                </div>
                <div>
                    <span>Total estimado con recojo</span>
                    <strong>Bs {{ $quote['total'] }}</strong>
                </div>
                <a class="btn-primary" href="{{ route('store.checkout.show') }}">Ir a checkout</a>
                <div class="store-pwa-summary__secondary">
                    <a class="btn-secondary" href="{{ route('store.catalog.index') }}">Tienda</a>
                    <form method="post" action="{{ route('store.cart.clear') }}">@csrf @method('delete')<button class="btn-secondary">Vaciar</button></form>
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
