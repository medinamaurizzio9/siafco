<x-layouts.app title="Mis Comunicados">
    <section class="section-card grid gap-4">
        <form class="grid items-end gap-3 sm:grid-cols-3">
            <label><span class="form-label">Buscar por título</span><input type="search" class="form-input" name="q" value="{{ request('q') }}"></label>
            <label><span class="form-label">Lectura</span><select class="form-input" name="filter">
                @foreach(['all'=>'Todos','read'=>'Leídos','unread'=>'No leídos'] as $key=>$label)
                    <option value="{{ $key }}" @selected(request('filter') === $key)>{{ $label }}</option>
                @endforeach
            </select></label>
            <div><button class="btn-secondary">Filtrar</button></div>
        </form>
        @forelse($items as $item)
            @include('communications.card')
        @empty <p>Sin comunicados recibidos.</p> @endforelse
        {{ $items->links() }}
    </section>
</x-layouts.app>
