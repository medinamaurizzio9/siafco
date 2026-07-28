<x-layouts.app title="Verificacion publica">
    <div class="mx-auto max-w-xl rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm">
        <div class="mx-auto mb-4 grid h-20 w-20 place-items-center overflow-hidden rounded border border-[#d4af37] bg-[#0b1f3a] text-xl font-black text-[#d4af37]">
            @if($institution->logoUrl())
                <img class="h-full w-full object-contain bg-white p-2" src="{{ $institution->logoUrl() }}" alt="Logo">
            @else
                SIAFCO
            @endif
        </div>
        @if($affiliate->trashed())
            <p class="text-sm font-black uppercase text-red-700">Credencial no válida</p>
        @else
            <p class="text-sm font-black uppercase text-[#b8942f]">Credencial verificada</p>
        @endif
        <h1 class="mt-2 text-3xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h1>
        @if($affiliate->trashed())
            <p class="mt-3 rounded border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-900">
                El afiliado fue dado de baja o eliminado del sistema.
            </p>
        @endif
        <dl class="mt-6 grid gap-4 text-left">
            <div><dt class="text-xs font-black uppercase text-slate-500">Registro</dt><dd>{{ $affiliate->registration_number }}</dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Sector</dt><dd>{{ $affiliate->sector->name }}</dd></div>
            <div>
                <dt class="text-xs font-black uppercase text-slate-500">Estado</dt>
                <dd>
                    @if($affiliate->trashed())
                        <span class="inline-flex rounded border border-red-200 bg-red-100 px-2 py-1 text-xs font-black uppercase text-red-900">Dado de baja</span>
                    @else
                        <x-affiliation-status :status="$affiliate->status" size="sm" />
                    @endif
                </dd>
            </div>
        </dl>
    </div>
</x-layouts.app>
