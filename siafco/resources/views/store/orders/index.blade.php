<x-layouts.app title="Mis pedidos">
    <form class="mb-5 grid gap-3 rounded bg-white p-4 shadow md:grid-cols-4" method="get">
        <input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Código">
        <select class="form-input" name="status"><option value="">Todos</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select>
        <input class="form-input" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        <button class="btn-secondary">Filtrar</button>
    </form>
    <div class="grid gap-3">
        @forelse($orders as $order)
            <article class="rounded bg-white p-4 shadow">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div><h3 class="font-black text-[#0b1f3a]">{{ $order->code }}</h3><p class="text-sm text-slate-600">{{ $order->created_at->format('d/m/Y H:i') }} · {{ $order->delivery_method }}</p></div>
                    <div class="grid gap-2 sm:flex sm:items-center sm:gap-3"><span class="rounded bg-[#fff8df] px-2 py-1 text-xs font-bold">{{ $order->status }}</span><strong>Bs {{ number_format((float) $order->total, 2) }}</strong><a class="btn-secondary min-h-12 w-full sm:w-auto" href="{{ route('store.orders.show', $order) }}">Ver</a></div>
                </div>
            </article>
        @empty
            <p class="rounded bg-white p-5 text-slate-600 shadow">No tienes pedidos registrados.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-layouts.app>
