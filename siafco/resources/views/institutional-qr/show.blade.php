<x-layouts.app title="QR institucional de pago">
    <div class="grid gap-5 lg:grid-cols-[360px_1fr]">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="mb-4 flex items-center gap-3">
                <div class="grid h-12 w-12 place-items-center overflow-hidden rounded border border-[#d4af37] bg-slate-50 text-sm font-black text-[#0b1f3a]">
                    @if($institution->logoUrl())
                        <img class="h-full w-full object-contain p-1" src="{{ $institution->logoUrl() }}" alt="Logo">
                    @else
                        S
                    @endif
                </div>
                <div>
                    <p class="font-black text-[#0b1f3a]">SIAFCO</p>
                    <p class="text-xs text-slate-500">{{ $institution->institution_name }}</p>
                </div>
            </div>
            <img class="mx-auto h-72 w-72 rounded border object-contain" src="{{ $institution->paymentQrUrl() }}" alt="QR bancario">
        </section>
        <form method="post" enctype="multipart/form-data" action="{{ route('institutional-qr.update') }}" class="rounded-lg border border-slate-200 bg-white p-5">
            @csrf
            <label class="form-label">Reemplazar QR bancario fijo</label>
            <input class="form-input" type="file" name="qr" accept="image/*" required>
            <button class="btn-primary mt-4">Actualizar QR</button>
            <a class="btn-secondary mt-4" href="{{ route('institutional-settings.edit') }}">Configuracion completa</a>
        </form>
    </div>
</x-layouts.app>
