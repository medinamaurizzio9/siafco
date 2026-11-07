<x-layouts.app title="Comunicaciones · Comunicados">
    <section class="section-card grid gap-4">
        <div><a class="btn-primary" href="{{ route('communications.create') }}">Crear comunicado</a></div>
        <form class="grid items-end gap-3 sm:grid-cols-3">
            <label><span class="form-label">Buscar por título</span><input type="search" class="form-input" name="q" value="{{ request('q') }}"></label>
            <label><span class="form-label">Estado</span><select class="form-input" name="status">
                <option value="">Todos los estados</option>
                @foreach(['draft','scheduled','published','archived'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('communications.'.$status, [], 'es') }}</option>
                @endforeach
            </select></label>
            <div><button class="btn-secondary">Filtrar</button></div>
        </form>
        @forelse($items as $item)
            <article class="grid gap-1 border-t pt-3">
                <a class="font-bold underline" href="{{ route('communications.preview', $item) }}">{{ $item->title }}</a>
                <span class="text-sm">{{ __('communications.'.$item->status, [], 'es') }}</span>
            </article>
        @empty <p>Sin comunicados.</p> @endforelse
        {{ $items->links() }}
    </section>
</x-layouts.app>
