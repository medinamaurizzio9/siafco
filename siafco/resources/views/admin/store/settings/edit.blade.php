<x-layouts.app title="Configuración de tienda">
    <form method="post" action="{{ route('admin.store.settings.update') }}" class="section-card mx-auto grid max-w-4xl gap-5 md:grid-cols-2">
        @csrf
        @method('put')

        <section class="md:col-span-2">
            <h2 class="text-xl font-black text-[#0b1f3a]">Mini tienda</h2>
            <p class="mt-1 text-sm text-slate-600">Los datos sensibles se conservan cifrados y solo se muestra una referencia enmascarada.</p>
        </section>

        <label class="flex items-center gap-2">
            <input type="checkbox" name="whatsapp_enabled" value="1" @checked(old('whatsapp_enabled', $setting->whatsapp_enabled))>
            WhatsApp habilitado
        </label>
        <div>
            <label class="form-label">WhatsApp actual</label>
            <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">{{ $setting->whatsapp_number_hint ?: 'Sin número configurado' }}</p>
        </div>

        <div>
            <label class="form-label">Reemplazar número de WhatsApp</label>
            <input class="form-input" name="whatsapp_number" value="" inputmode="tel" autocomplete="off" placeholder="Ej. 70000000 o +59170000000">
        </div>
        <label class="flex items-center gap-2 pt-7">
            <input type="checkbox" name="remove_whatsapp_number" value="1">
            Eliminar número configurado
        </label>

        <label class="flex items-center gap-2">
            <input type="checkbox" name="pickup_enabled" value="1" @checked(old('pickup_enabled', $setting->pickup_enabled))>
            Recojo en oficina habilitado
        </label>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="shipping_enabled" value="1" @checked(old('shipping_enabled', $setting->shipping_enabled))>
            Envío nacional habilitado
        </label>

        <div>
            <label class="form-label">Moneda</label>
            <select class="form-input" name="default_currency">
                <option value="BOB" @selected(old('default_currency', $setting->default_currency ?: 'BOB') === 'BOB')>BOB</option>
            </select>
        </div>
        <div>
            <label class="form-label">Tamaño máximo de comprobante (KB)</label>
            <input class="form-input" type="number" name="max_receipt_size_kb" min="256" max="10240" value="{{ old('max_receipt_size_kb', $setting->max_receipt_size_kb ?: 6144) }}" required>
        </div>

        <div>
            <label class="form-label">Instrucciones de recojo</label>
            <textarea class="form-input" name="pickup_instructions" rows="4">{{ old('pickup_instructions', $setting->pickup_instructions) }}</textarea>
        </div>
        <div>
            <label class="form-label">Instrucciones de envío</label>
            <textarea class="form-input" name="shipping_instructions" rows="4">{{ old('shipping_instructions', $setting->shipping_instructions) }}</textarea>
        </div>

        <div class="flex gap-3 md:col-span-2">
            <button class="btn-primary">Guardar configuración</button>
            <a class="btn-secondary" href="{{ route('admin.store.dashboard') }}">Volver</a>
        </div>
    </form>
</x-layouts.app>
