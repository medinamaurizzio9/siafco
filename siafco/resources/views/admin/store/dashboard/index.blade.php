<x-layouts.app title="Mini tienda">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Resumen de mini tienda</h2>
            <p class="text-sm text-slate-600">Administración de catálogo, disponibilidad y configuración comercial.</p>
        </div>
        @can('store.manage-settings')
            <a class="btn-primary" href="{{ route('admin.store.settings.edit') }}">Configurar tienda</a>
        @endcan
    </div>

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="metric-card"><p>Productos activos</p><strong>{{ $metrics['active_products'] }}</strong></div>
        <div class="metric-card"><p>Destacados</p><strong>{{ $metrics['featured_products'] }}</strong></div>
        <div class="metric-card"><p>Categorías</p><strong>{{ $metrics['categories'] }}</strong></div>
        <div class="metric-card"><p>Agotados</p><strong>{{ $metrics['sold_out_products'] }}</strong></div>
        <div class="metric-card"><p>Tarifas activas</p><strong>{{ $metrics['active_shipping_rates'] }}</strong></div>
    </section>

    <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <article class="section-card">
            <p class="text-xs font-black uppercase text-[#b8942f]">WhatsApp</p>
            <h3 class="mt-2 text-lg font-black text-[#0b1f3a]">{{ $setting->whatsapp_enabled ? 'Activo' : 'Inactivo' }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ $setting->whatsapp_number_hint ?: 'Sin número configurado' }}</p>
        </article>
        <article class="section-card">
            <p class="text-xs font-black uppercase text-[#b8942f]">Recojo en oficina</p>
            <h3 class="mt-2 text-lg font-black text-[#0b1f3a]">{{ $setting->pickup_enabled ? 'Habilitado' : 'Deshabilitado' }}</h3>
        </article>
        <article class="section-card">
            <p class="text-xs font-black uppercase text-[#b8942f]">Envío nacional</p>
            <h3 class="mt-2 text-lg font-black text-[#0b1f3a]">{{ $setting->shipping_enabled ? 'Habilitado' : 'Deshabilitado' }}</h3>
        </article>
    </section>
</x-layouts.app>
