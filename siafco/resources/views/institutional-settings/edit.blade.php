<x-layouts.app title="Configuracion institucional">
    <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
        <form method="post" enctype="multipart/form-data" action="{{ route('institutional-settings.update') }}" class="section-card grid gap-4 md:grid-cols-2">
            @csrf
            @method('put')
            <div class="md:col-span-2">
                <label class="form-label">Nombre institucion</label>
                <input class="form-input" name="institution_name" value="{{ old('institution_name', $setting->institution_name) }}" data-uppercase required>
            </div>
            <div>
                <label class="form-label">Color principal</label>
                <input class="form-input h-12" type="color" name="primary_color" value="{{ old('primary_color', $setting->primary_color) }}" required>
            </div>
            <div>
                <label class="form-label">Color secundario</label>
                <input class="form-input h-12" type="color" name="secondary_color" value="{{ old('secondary_color', $setting->secondary_color) }}" required>
            </div>
            <div>
                <label class="form-label">Correo</label>
                <input class="form-input" type="email" name="email" value="{{ old('email', $setting->email) }}">
            </div>
            <div>
                <label class="form-label">Telefono</label>
                <input class="form-input" name="phone" value="{{ old('phone', $setting->phone) }}">
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Direccion</label>
                <input class="form-input" name="address" value="{{ old('address', $setting->address) }}" data-uppercase>
            </div>
            <div>
                <label class="form-label">Logo</label>
                <input class="form-input" type="file" name="logo" accept="image/*">
            </div>
            <div>
                <label class="form-label">QR bancario fijo</label>
                <input class="form-input" type="file" name="payment_qr" accept="image/png,image/jpeg,image/webp">
            </div>
            <div><label class="form-label">Banco</label><input class="form-input" name="payment_bank" value="{{ old('payment_bank',$setting->payment_bank) }}" data-uppercase></div>
            <div><label class="form-label">Titular</label><input class="form-input" name="payment_holder" value="{{ old('payment_holder',$setting->payment_holder) }}" data-uppercase></div>
            <div><label class="form-label">Cuenta (opcional)</label><input class="form-input" name="payment_account" value="{{ old('payment_account',$setting->payment_account) }}"></div>
            <div class="md:col-span-2"><label class="form-label">Instrucciones de pago</label><textarea class="form-input" name="payment_instructions" rows="3" data-uppercase>{{ old('payment_instructions',$setting->payment_instructions) }}</textarea></div>
            <div class="md:col-span-2">
                <button class="btn-primary">Guardar configuracion</button>
            </div>
        </form>

        <aside class="section-card">
            <h2 class="text-xl font-black text-[#0b1f3a]">Vista actual</h2>
            <div class="mt-4 grid place-items-center rounded border border-slate-200 bg-slate-50 p-5">
                @if($setting->logoUrl())
                    <img class="h-28 w-28 object-contain" src="{{ $setting->logoUrl() }}" alt="Logo">
                @else
                    <div class="grid h-28 w-28 place-items-center rounded bg-[#0b1f3a] font-black text-[#d4af37]">SIAFCO</div>
                @endif
            </div>
            <dl class="mt-5 grid gap-3 text-sm">
                <div><dt class="font-black text-slate-500">Institucion</dt><dd>{{ $setting->institution_name }}</dd></div>
                <div><dt class="font-black text-slate-500">Correo</dt><dd>{{ $setting->email ?: 'Sin dato' }}</dd></div>
                <div><dt class="font-black text-slate-500">Telefono</dt><dd>{{ $setting->phone ?: 'Sin dato' }}</dd></div>
            </dl>
            <img class="mt-5 h-48 w-48 rounded border object-contain" src="{{ $setting->paymentQrUrl() }}" alt="QR pago">
        </aside>
    </div>
</x-layouts.app>
