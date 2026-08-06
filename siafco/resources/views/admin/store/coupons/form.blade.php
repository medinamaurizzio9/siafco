<x-layouts.app title="{{ $coupon->exists ? 'Editar cupón' : 'Nuevo cupón' }}">
    <form method="post" action="{{ $coupon->exists ? route('admin.store.coupons.update', $coupon) : route('admin.store.coupons.store') }}" class="section-card grid gap-4 md:grid-cols-2">
        @csrf
        @if($coupon->exists) @method('put') @endif
        <div>
            <label class="form-label">Código {{ $coupon->exists ? '(dejar vacío para conservar)' : '' }}</label>
            <input class="form-input" name="code" value="" autocomplete="off" {{ $coupon->exists ? '' : 'required' }}>
            @if($coupon->exists)<p class="mt-1 text-xs text-slate-500">Actual: {{ $coupon->code_hint }}</p>@endif
        </div>
        <div>
            <label class="form-label">Tipo</label>
            <select class="form-input" name="type" required>
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', $coupon->type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="form-label">Valor</label><input class="form-input" type="number" step="0.01" min="0.01" name="value" value="{{ old('value', $coupon->value) }}" required></div>
        <div><label class="form-label">Compra mínima</label><input class="form-input" type="number" step="0.01" min="0" name="minimum_amount" value="{{ old('minimum_amount', $coupon->minimum_amount ?? 0) }}"></div>
        <div><label class="form-label">Inicio</label><input class="form-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\TH:i')) }}"></div>
        <div><label class="form-label">Vencimiento</label><input class="form-input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $coupon->ends_at?->format('Y-m-d\TH:i')) }}"></div>
        <div><label class="form-label">Límite global</label><input class="form-input" type="number" min="1" name="global_limit" value="{{ old('global_limit', $coupon->global_limit) }}"></div>
        <div><label class="form-label">Límite por afiliado</label><input class="form-input" type="number" min="1" name="per_affiliate_limit" value="{{ old('per_affiliate_limit', $coupon->per_affiliate_limit) }}"></div>
        <label class="flex items-center gap-2 md:col-span-2"><input type="checkbox" name="active" value="1" @checked(old('active', $coupon->active ?? true))> Activo</label>

        <fieldset class="rounded border border-slate-200 p-3 md:col-span-2">
            <legend class="px-1 text-sm font-bold text-slate-700">Productos objetivo</legend>
            <div class="grid gap-2 md:grid-cols-2">
                @foreach($products as $product)
                    <label class="flex items-center gap-2"><input type="checkbox" name="target_products[]" value="{{ $product->id }}" @checked(in_array($product->id, old('target_products', $selectedProducts), true))> {{ $product->sku }} - {{ $product->name }}</label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="rounded border border-slate-200 p-3 md:col-span-2">
            <legend class="px-1 text-sm font-bold text-slate-700">Categorías objetivo</legend>
            <div class="grid gap-2 md:grid-cols-2">
                @foreach($categories as $category)
                    <label class="flex items-center gap-2"><input type="checkbox" name="target_categories[]" value="{{ $category->id }}" @checked(in_array($category->id, old('target_categories', $selectedCategories), true))> {{ $category->name }}</label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">Sin objetivos seleccionados, el cupón aplica globalmente.</p>
        </fieldset>

        <div class="flex gap-3 md:col-span-2">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('admin.store.coupons.index') }}">Volver</a>
        </div>
    </form>
    @if($coupon->exists)
        <form class="mt-4" method="post" action="{{ route('admin.store.coupons.destroy', $coupon) }}">
            @csrf @method('delete')
            <button class="btn-danger" onclick="return confirm('¿Eliminar este cupón?')">Eliminar cupón</button>
        </form>
    @endif
</x-layouts.app>
