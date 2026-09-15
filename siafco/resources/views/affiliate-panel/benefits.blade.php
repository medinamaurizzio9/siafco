<x-layouts.app title="Beneficios">
    @php
        $activeStore = auth()->user()->user_type === 'affiliate' && auth()->user()->is_active && $affiliate->status === 'activo';
        $categories = $benefits->pluck('benefit_type')->filter()->map(fn ($type) => str($type)->replace('_', ' ')->headline()->toString())->unique()->values();
    @endphp
    <div class="affiliate-screen">
        <section class="affiliate-benefits-hero">
            <div>
                <h2>Beneficios para una mejor vida</h2>
                <p>Accede a convenios, descuentos y servicios exclusivos.</p>
            </div>
        </section>

        <nav class="affiliate-tabs" aria-label="Categorías de beneficios">
            <a class="is-active" href="{{ route('affiliate.benefits') }}">Todos</a>
            @foreach($categories as $category)
                <span>{{ $category }}</span>
            @endforeach
        </nav>

        <section class="affiliate-benefit-list">
            @forelse($benefits as $benefit)
                @php
                    $href = $benefit->route_name && Route::has($benefit->route_name) ? route($benefit->route_name) : $benefit->external_url;
                    $type = str($benefit->benefit_type ?: 'Beneficio')->replace('_', ' ')->headline();
                @endphp
                @if($href)
                    <a class="affiliate-benefit-card" href="{{ $href }}" @if($benefit->external_url) target="_blank" rel="noopener" @endif>
                        <span><x-ui.icon :name="$benefit->icon ?: 'gift'" class="h-6 w-6" /></span>
                        <div><strong>{{ $benefit->title }}</strong><small>{{ $type }}</small><p>{{ $benefit->description }}</p></div>
                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                @else
                    <article class="affiliate-benefit-card">
                        <span><x-ui.icon :name="$benefit->icon ?: 'gift'" class="h-6 w-6" /></span>
                        <div><strong>{{ $benefit->title }}</strong><small>{{ $type }}</small><p>{{ $benefit->description }}</p></div>
                    </article>
                @endif
            @empty
                <div class="affiliate-empty-state">
                    <x-ui.icon name="gift" class="h-7 w-7" />
                    <strong>Beneficios en preparación</strong>
                    <p>La estructura está lista para mostrar convenios reales cuando sean cargados por administración.</p>
                </div>
            @endforelse
        </section>

        @if($activeStore)
            <section class="affiliate-feature-card affiliate-feature-card--store">
                <div><h3>Mini tienda</h3><p>Productos, pedidos y carrito siguen disponibles para afiliados activos.</p></div>
                <a href="{{ route('store.catalog.index') }}" aria-label="Abrir mini tienda"><x-ui.icon name="arrow-right" class="h-5 w-5" /></a>
            </section>
        @endif
    </div>
</x-layouts.app>
