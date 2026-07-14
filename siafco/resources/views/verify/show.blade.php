<x-layouts.app title="Verificacion publica">
    <div class="mx-auto max-w-xl rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm">
        <div class="mx-auto mb-4 grid h-20 w-20 place-items-center overflow-hidden rounded border border-[#d4af37] bg-[#0b1f3a] text-xl font-black text-[#d4af37]">
            @if($institution->logoUrl())
                <img class="h-full w-full object-contain bg-white p-2" src="{{ $institution->logoUrl() }}" alt="Logo">
            @else
                SIAFCO
            @endif
        </div>
        <p class="text-sm font-black uppercase text-[#b8942f]">Credencial verificada</p>
        <h1 class="mt-2 text-3xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h1>
        <dl class="mt-6 grid gap-4 text-left">
            <div><dt class="text-xs font-black uppercase text-slate-500">Registro</dt><dd>{{ $affiliate->registration_number }}</dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Sector</dt><dd>{{ $affiliate->sector->name }}</dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Estado</dt><dd><span class="badge">{{ $affiliate->status }}</span></dd></div>
        </dl>
    </div>
</x-layouts.app>
