<x-layouts.app title="Mini tienda">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Dashboard de Mini Tienda</h2>
            <p class="text-sm text-slate-600">Panorama operativo y comercial con datos reales de pedidos, ventas y catalogo.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('admin.store.orders.index') }}">Ver todos los pedidos</a>
            @can('store.manage-settings')
                <a class="btn-primary" href="{{ route('admin.store.settings.edit') }}">Configurar tienda</a>
            @endcan
        </div>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="metric-card"><p>Ventas confirmadas</p><strong>{{ $storeMetrics['confirmed_sales'] }}</strong></div>
        <div class="metric-card"><p>Monto vendido</p><strong>Bs {{ number_format($storeMetrics['sold_amount'], 2) }}</strong></div>
        <div class="metric-card"><p>Pedidos pendientes</p><strong>{{ $storeMetrics['pending_orders'] }}</strong></div>
        <div class="metric-card"><p>Pagos en revision</p><strong>{{ $storeMetrics['payment_review_orders'] }}</strong></div>
        <div class="metric-card"><p>Pedidos del dia</p><strong>{{ $storeMetrics['today_orders'] }}</strong></div>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="section-card"><p class="text-xs font-black uppercase text-[#b8942f]">Productos activos</p><strong class="mt-2 block text-2xl text-[#0b1f3a]">{{ $metrics['active_products'] }}</strong></div>
        <div class="section-card"><p class="text-xs font-black uppercase text-[#b8942f]">Destacados</p><strong class="mt-2 block text-2xl text-[#0b1f3a]">{{ $metrics['featured_products'] }}</strong></div>
        <div class="section-card"><p class="text-xs font-black uppercase text-[#b8942f]">Categorias</p><strong class="mt-2 block text-2xl text-[#0b1f3a]">{{ $metrics['categories'] }}</strong></div>
        <div class="section-card"><p class="text-xs font-black uppercase text-[#b8942f]">Agotados</p><strong class="mt-2 block text-2xl text-[#0b1f3a]">{{ $metrics['sold_out_products'] }}</strong></div>
        <div class="section-card"><p class="text-xs font-black uppercase text-[#b8942f]">Tarifas activas</p><strong class="mt-2 block text-2xl text-[#0b1f3a]">{{ $metrics['active_shipping_rates'] }}</strong></div>
    </section>

    <section class="section-card mt-6">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-black text-[#0b1f3a]">Pedidos recientes</h3>
                <p class="text-sm text-slate-600">Ultimos movimientos operativos de la Mini Tienda.</p>
            </div>
            <a class="btn-secondary" href="{{ route('admin.store.orders.index') }}">Ver todos los pedidos</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table min-w-full">
                <thead><tr><th>Pedido</th><th>Afiliado</th><th>Estado</th><th>Entrega</th><th>Total</th><th>Fecha</th><th></th></tr></thead>
                <tbody>
                @forelse($recentOrders as $order)
                    <tr>
                        <td class="font-bold text-[#0b1f3a]">{{ $order->code }}</td>
                        <td>{{ $order->affiliate?->full_name ?: 'Sin afiliado' }}</td>
                        <td><span class="rounded bg-slate-100 px-2 py-1 text-xs font-bold">{{ $order->status }}</span></td>
                        <td>{{ $order->delivery_method }}</td>
                        <td>Bs {{ number_format((float) $order->total, 2) }}</td>
                        <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-right"><a class="btn-secondary" href="{{ route('admin.store.orders.show', $order) }}">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No hay pedidos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <article class="section-card">
            <p class="text-xs font-black uppercase text-[#b8942f]">WhatsApp</p>
            <h3 class="mt-2 text-lg font-black text-[#0b1f3a]">{{ $setting->whatsapp_enabled ? 'Activo' : 'Inactivo' }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ $setting->whatsapp_number_hint ?: 'Sin numero configurado' }}</p>
        </article>
        <article class="section-card">
            <p class="text-xs font-black uppercase text-[#b8942f]">Recojo en oficina</p>
            <h3 class="mt-2 text-lg font-black text-[#0b1f3a]">{{ $setting->pickup_enabled ? 'Habilitado' : 'Deshabilitado' }}</h3>
        </article>
        <article class="section-card">
            <p class="text-xs font-black uppercase text-[#b8942f]">Envio nacional</p>
            <h3 class="mt-2 text-lg font-black text-[#0b1f3a]">{{ $setting->shipping_enabled ? 'Habilitado' : 'Deshabilitado' }}</h3>
        </article>
    </section>
</x-layouts.app>
