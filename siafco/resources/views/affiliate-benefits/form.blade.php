<x-layouts.app title="{{ $benefit->exists ? 'Editar servicio' : 'Nuevo servicio' }}">
    <form class="section-card mx-auto grid max-w-3xl gap-4 sm:grid-cols-2" method="post" action="{{ $benefit->exists ? route('affiliate-benefits.update',$benefit) : route('affiliate-benefits.store') }}">
        @csrf @if($benefit->exists) @method('put') @endif
        <label><span class="form-label">Título</span><input class="form-input" name="title" value="{{ old('title',$benefit->title) }}" data-uppercase required></label>
        <label><span class="form-label">Icono</span><select class="form-input" name="icon">@foreach(['card'=>'Credencial','credit'=>'Crédito','calculator'=>'Calculadora','history'=>'Historial','gift'=>'Beneficio','news'=>'Noticias','support'=>'Soporte','investment'=>'Inversión'] as $value=>$label)<option value="{{ $value }}" @selected(old('icon',$benefit->icon)===$value)>{{ $label }}</option>@endforeach</select></label>
        <label class="sm:col-span-2"><span class="form-label">Descripción</span><textarea class="form-input" name="description" rows="3" data-uppercase>{{ old('description',$benefit->description) }}</textarea></label>
        <label><span class="form-label">Nombre de ruta (opcional)</span><input class="form-input" name="route_name" value="{{ old('route_name',$benefit->route_name) }}"></label>
        <label><span class="form-label">URL externa (opcional)</span><input class="form-input" type="url" name="external_url" value="{{ old('external_url',$benefit->external_url) }}"></label>
        <label><span class="form-label">Orden</span><input class="form-input" type="number" min="0" name="order" value="{{ old('order',$benefit->order ?? 0) }}" required></label>
        <div class="grid gap-3 pt-6"><label class="flex gap-2"><input type="checkbox" name="active" value="1" @checked(old('active',$benefit->active ?? true))> Activo</label><label class="flex gap-2"><input type="checkbox" name="visible_when_pending" value="1" @checked(old('visible_when_pending',$benefit->visible_when_pending ?? true))> Mostrar bloqueado cuando esté pendiente</label></div>
        <div class="flex gap-3 sm:col-span-2"><button class="btn-primary">Guardar</button><a class="btn-secondary" href="{{ route('affiliate-benefits.index') }}">Volver</a></div>
    </form>
</x-layouts.app>
