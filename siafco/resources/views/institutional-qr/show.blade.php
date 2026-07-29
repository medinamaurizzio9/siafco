<x-layouts.app title="QR y pago institucional">
    <div class="grid gap-5 lg:grid-cols-[360px_1fr]">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-xs font-black uppercase text-[#b8942f]">Fuente oficial</p>
            <h2 class="mt-1 text-xl font-black text-[#0b1f3a]">QR institucional de pago</h2>
            @if($setting->paymentQrUrl())
                <img class="mx-auto mt-5 aspect-square w-full max-w-72 rounded border object-contain" src="{{ $setting->paymentQrUrl() }}" alt="QR institucional de pago">
            @else
                <div class="mt-5 grid aspect-square w-full max-w-72 place-items-center rounded border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm font-semibold text-slate-500">
                    No existe un QR institucional de pago configurado.
                </div>
            @endif
            <dl class="mt-5 grid gap-3 text-sm">
                <div><dt class="font-bold text-slate-500">Banco</dt><dd>{{ $setting->payment_bank ?: 'No registrado' }}</dd></div>
                <div><dt class="font-bold text-slate-500">Titular</dt><dd>{{ $setting->payment_holder ?: 'No registrado' }}</dd></div>
                <div><dt class="font-bold text-slate-500">Cuenta</dt><dd>{{ $setting->payment_account ?: 'No registrada' }}</dd></div>
            </dl>
        </section>

        <form method="post" enctype="multipart/form-data" action="{{ route('institutional-qr.update') }}" class="rounded-lg border border-slate-200 bg-white p-5">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="form-label" for="qr">Reemplazar QR institucional</label>
                    <input id="qr" class="form-input" type="file" name="qr" accept="image/jpeg,image/png,image/webp">
                    <p class="mt-1 text-xs text-slate-500">JPG, JPEG, PNG o WEBP. Máximo 5 MB.</p>
                    @error('qr')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                @if($setting->payment_qr_path)
                    <label class="sm:col-span-2 flex items-center gap-2 rounded bg-slate-50 p-3 text-sm font-semibold">
                        <input type="checkbox" name="remove_qr" value="1"> Eliminar el QR actual
                    </label>
                @endif
                <label><span class="form-label">Banco</span><input class="form-input" name="payment_bank" value="{{ old('payment_bank', $setting->payment_bank) }}" data-uppercase></label>
                <label><span class="form-label">Titular</span><input class="form-input" name="payment_holder" value="{{ old('payment_holder', $setting->payment_holder) }}" data-uppercase></label>
                <label class="sm:col-span-2"><span class="form-label">Cuenta</span><input class="form-input" name="payment_account" value="{{ old('payment_account', $setting->payment_account) }}"></label>
                <label class="sm:col-span-2"><span class="form-label">Instrucciones de pago</span><textarea class="form-input" name="payment_instructions" rows="4" data-uppercase>{{ old('payment_instructions', $setting->payment_instructions) }}</textarea></label>
            </div>
            <button class="btn-primary mt-5 w-full sm:w-auto">GUARDAR QR Y DATOS DE PAGO</button>
        </form>
    </div>
</x-layouts.app>
