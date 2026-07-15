<x-layouts.app title="Configuracion de inversiones">
    <form class="section-card grid gap-4 md:grid-cols-2" method="post" enctype="multipart/form-data" action="{{ route('investments.settings.update') }}">
        @csrf
        @method('put')
        <div>
            <label class="form-label">Nombre comercial</label>
            <input class="form-input" name="company_name" value="{{ old('company_name', $setting->company_name) }}" required>
        </div>
        <div>
            <label class="form-label">Razon social</label>
            <input class="form-input" name="company_legal_name" value="{{ old('company_legal_name', $setting->company_legal_name) }}">
        </div>
        <div><label class="form-label">NIT</label><input class="form-input" name="nit" value="{{ old('nit', $setting->nit) }}"></div>
        <div><label class="form-label">Correo</label><input class="form-input" type="email" name="email" value="{{ old('email', $setting->email) }}"></div>
        <div><label class="form-label">Telefono</label><input class="form-input" name="phone" value="{{ old('phone', $setting->phone) }}"></div>
        <div><label class="form-label">Direccion</label><input class="form-input" name="address" value="{{ old('address', $setting->address) }}"></div>
        <div><label class="form-label">Logo recibos</label><input class="form-input" type="file" name="receipt_logo" accept="image/png,image/jpeg,image/webp"></div>
        <div><label class="form-label">Moneda</label><input class="form-input" name="currency" value="{{ old('currency', $setting->currency) }}" required></div>
        <div><label class="form-label">Precio por accion</label><input class="form-input" type="number" step="0.01" name="share_unit_price" value="{{ old('share_unit_price', $setting->share_unit_price) }}" required></div>
        <div><label class="form-label">Minimo acciones</label><input class="form-input" type="number" name="minimum_shares" value="{{ old('minimum_shares', $setting->minimum_shares) }}" required></div>
        <div><label class="form-label">Maximo acciones</label><input class="form-input" type="number" name="maximum_shares" value="{{ old('maximum_shares', $setting->maximum_shares) }}" required></div>
        <div><label class="form-label">Rendimiento mensual %</label><input class="form-input" type="number" step="0.01" name="monthly_return_percentage" value="{{ old('monthly_return_percentage', $setting->monthly_return_percentage) }}" required></div>
        <div><label class="form-label">Meses de espera</label><input class="form-input" type="number" name="waiting_months" value="{{ old('waiting_months', $setting->waiting_months) }}" required></div>
        <div><label class="form-label">Anios de contrato</label><input class="form-input" type="number" name="contract_years" value="{{ old('contract_years', $setting->contract_years) }}" required></div>
        <div><label class="form-label">Dias reserva</label><input class="form-input" type="number" name="reservation_days" value="{{ old('reservation_days', $setting->reservation_days) }}" required></div>
        <div><label class="form-label">Prefijo recibo</label><input class="form-input" name="receipt_prefix" value="{{ old('receipt_prefix', $setting->receipt_prefix) }}" required></div>
        <div><label class="form-label">Siguiente recibo</label><input class="form-input" type="number" name="next_receipt_number" value="{{ old('next_receipt_number', $setting->next_receipt_number) }}" required></div>
        <div><label class="form-label">Alerta maduracion dias</label><input class="form-input" type="number" name="alert_days_before_maturity" value="{{ old('alert_days_before_maturity', $setting->alert_days_before_maturity) }}" required></div>
        <div class="grid gap-2">
            @foreach(['maximum_shares_per_person'=>'Maximo por persona','renewal_enabled'=>'Renovacion habilitada','production_bonus_enabled'=>'Bono produccion','extra_amount_enabled'=>'Extras manuales','active'=>'Configuracion activa'] as $field => $label)
                <label class="flex items-center gap-2 font-bold"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $setting->{$field}))> {{ $label }}</label>
            @endforeach
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Texto legal recibo</label>
            <textarea class="form-input" name="receipt_legal_text" rows="4">{{ old('receipt_legal_text', $setting->receipt_legal_text) }}</textarea>
        </div>
        <div class="md:col-span-2"><button class="btn-primary">Guardar configuracion</button></div>
    </form>
</x-layouts.app>
