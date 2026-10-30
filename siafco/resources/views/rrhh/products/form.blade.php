<x-layouts.app :title="$product->exists ? 'Editar producto' : 'Nuevo producto'">
@php($editing=$product->exists)
@php($value=fn($name,$fallback=null)=>old($name,data_get($product,$name,$fallback)))
<div class="mx-auto max-w-4xl space-y-5">
    <div><h1 class="text-2xl font-black text-siafco-primary-900">{{ $editing?'Editar producto':'Nuevo producto' }}</h1><p class="text-slate-600">Configura el producto y su regla de comisión.</p></div>
    <form method="post" action="{{ $editing?route('rrhh.products.update',$product):route('rrhh.products.store') }}" class="space-y-5" data-product-form>@csrf @if($editing)@method('PATCH')@endif
        <x-ui.card>
            <div class="mb-4 border-b border-slate-200 pb-3"><h2 class="text-lg font-black text-siafco-primary-900">Información del producto</h2><p class="text-sm text-slate-500">Identificación, precio de referencia y descripción.</p></div>
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="form-label">Código *</span><input class="form-control" name="code" value="{{ $value('code') }}" required>@error('code')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">Nombre *</span><input class="form-control" name="name" value="{{ $value('name') }}" required>@error('name')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">Precio referencial (BOB) *</span><input class="form-control" type="number" min="0" step="0.01" name="reference_price" value="{{ $value('reference_price') }}" required>@error('reference_price')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="md:col-span-2"><span class="form-label">Descripción</span><textarea class="form-control" name="description" rows="4">{{ $value('description') }}</textarea>@error('description')<span class="form-error">{{ $message }}</span>@enderror</label>
            </div>
        </x-ui.card>
        <x-ui.card>
            <div class="mb-4 border-b border-slate-200 pb-3"><h2 class="text-lg font-black text-siafco-primary-900">Configuración de comisión</h2><p class="text-sm text-slate-500">La comisión se calcula al confirmar la venta y se conserva históricamente.</p></div>
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="form-label">Tipo de comisión *</span><select class="form-control" name="commission_type" required data-commission-type><option value="fixed" @selected($value('commission_type')==='fixed')>Monto fijo por unidad</option><option value="percentage" @selected($value('commission_type')==='percentage')>Porcentaje de la línea</option></select>@error('commission_type')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label" data-commission-label>Comisión por unidad (Bs) *</span><input class="form-control" type="number" min="0" step="0.0001" name="commission_value" value="{{ $value('commission_value') }}" required>@error('commission_value')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-slate-300 bg-white px-4 py-3 md:col-span-2"><input type="hidden" name="is_active" value="0"><input class="size-4 accent-siafco-secondary-500" type="checkbox" name="is_active" value="1" @checked(old('is_active',$product->is_active??true))><span class="font-semibold text-slate-700">Producto activo</span></label>
            </div>
        </x-ui.card>
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><x-ui.button href="{{ route('rrhh.products.index') }}" variant="outline">Cancelar</x-ui.button><x-ui.button type="submit">{{ $editing?'Guardar cambios':'Registrar producto' }}</x-ui.button></div>
    </form>
</div>
@push('scripts')<script>document.addEventListener('DOMContentLoaded',()=>{const type=document.querySelector('[data-commission-type]');const label=document.querySelector('[data-commission-label]');const sync=()=>{label.textContent=type.value==='percentage'?'Comisión (%) *':'Comisión por unidad (Bs) *';};type.addEventListener('change',sync);sync();});</script>@endpush
</x-layouts.app>
