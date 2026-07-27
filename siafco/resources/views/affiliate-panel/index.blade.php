<x-layouts.app title="Panel afiliado">
    @if(!$affiliate)
        <div class="section-card">No existe una ficha de afiliado vinculada a este usuario.</div>
    @elseif(!$isActive)
        @php($application = $affiliate->publicRequest)
        <div class="mx-auto max-w-4xl space-y-6">
            <section class="section-card">
                <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div><p class="text-sm font-black uppercase text-[#b8942f]">{{ $application?->request_code ?: 'Solicitud en proceso' }}</p><h2 class="text-2xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h2></div>
                    <span class="badge">{{ str_replace('_', ' ', $application?->status ?: $affiliate->status) }}</span>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-sm font-bold text-slate-500">Plan seleccionado</dt><dd class="font-bold">{{ $affiliate->plan->name }}</dd></div>
                    <div><dt class="text-sm font-bold text-slate-500">Monto</dt><dd class="font-bold">BOB {{ number_format($application?->amount_due ?? $affiliate->payments->first()?->amount, 2) }}</dd></div>
                    <div><dt class="text-sm font-bold text-slate-500">Transacción</dt><dd>{{ $application?->payment?->transaction_number ?: 'Aún no registrada' }}</dd></div>
                    <div><dt class="text-sm font-bold text-slate-500">Comprobante</dt><dd>{{ $application?->payment?->voucher_path ? 'Enviado' : 'No enviado' }}</dd></div>
                </dl>
                @if($application?->rejection_reason || $application?->observations)
                    <div class="mt-5 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">{{ $application->rejection_reason ?: $application->observations }}</div>
                @endif
                <ol class="mt-7 grid gap-3 sm:grid-cols-4">
                    @foreach(['Solicitud registrada','Pago enviado','Revisión de Secretaría','Activación y credencial'] as $index => $step)
                        @php($done = $index < 2 && $application?->payment_submitted_at)
                        @php($current = $index === 2)
                        <li class="border-t-4 p-3 text-sm font-bold {{ $done ? 'border-[#d4af37] bg-[#fff8df]' : ($current ? 'border-[#0b1f3a] bg-slate-100' : 'border-slate-200 text-slate-400') }}">{{ $index + 1 }}. {{ $step }}</li>
                    @endforeach
                </ol>
                @if($application)<a class="btn-primary mt-6 w-full sm:w-auto" href="{{ route('public-affiliation.status',$application) }}">Consultar estado</a>@endif
            </section>

            <section>
                <h2 class="text-xl font-black text-[#0b1f3a]">Mis servicios y beneficios</h2>
                <p class="mt-1 text-sm text-slate-600">Estos servicios estarán disponibles cuando Secretaría confirme tu pago.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($benefits as $benefit)
                        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4 opacity-70">
                            <span class="grid h-10 w-10 place-items-center rounded bg-slate-200 font-black text-slate-500">×</span>
                            <h3 class="mt-3 font-black text-slate-700">{{ $benefit->title }}</h3><p class="mt-1 text-sm text-slate-500">{{ $benefit->description }}</p>
                            <span class="mt-3 inline-block text-xs font-black uppercase text-slate-400">Bloqueado</span>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    @else
        <div class="space-y-7">
            <section class="grid gap-5 lg:grid-cols-[1fr_380px]">
                <div class="section-card">
                    <p class="text-sm font-black uppercase text-[#b8942f]">{{ $affiliate->registration_number }}</p>
                    <h2 class="text-3xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2"><div class="metric-card"><p>Estado</p><strong class="text-xl">ACTIVO</strong></div><div class="metric-card"><p>Sector</p><strong class="text-xl">{{ $affiliate->sector->name }}</strong></div></div>
                </div>
                <aside class="section-card">
                    <h3 class="font-black text-[#0b1f3a]">Credencial digital</h3>
                    @if($affiliate->credential)
                        <img class="mt-4 w-full rounded border shadow-sm" src="{{ Storage::url($affiliate->credential->png_path) }}" alt="Credencial digital">
                        <div class="mt-4 grid gap-2"><a class="btn-primary" href="{{ route('affiliate.credential.pdf') }}">Descargar PDF</a><a class="btn-secondary" href="{{ route('affiliate.credential.png') }}">Descargar PNG</a><a class="btn-secondary" href="{{ route('affiliate.credential.preview') }}">Ver e imprimir</a></div>
                    @else
                        <p class="mt-3 text-sm text-slate-600">La credencial se está preparando.</p>
                    @endif
                </aside>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#0b1f3a]">Mis servicios y beneficios</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($benefits as $benefit)
                        @php($href = $benefit->route_name && Route::has($benefit->route_name) ? route($benefit->route_name) : $benefit->external_url)
                        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <span class="grid h-10 w-10 place-items-center rounded bg-[#fff8df] font-black uppercase text-[#0b1f3a]">{{ mb_substr($benefit->title,0,1) }}</span>
                            <h3 class="mt-3 font-black text-[#0b1f3a]">{{ $benefit->title }}</h3><p class="mt-1 text-sm text-slate-600">{{ $benefit->description }}</p>
                            @if($href)<a class="mt-4 inline-block text-sm font-black text-[#0b1f3a] underline" href="{{ $href }}" @if($benefit->external_url) target="_blank" rel="noopener" @endif>Abrir</a>@else<span class="mt-4 inline-block text-xs font-black uppercase text-slate-400">Próximamente</span>@endif
                        </article>
                    @endforeach
                    @if(auth()->user()->investor)
                        <article class="rounded-lg border border-[#d4af37] bg-white p-4 shadow-sm"><span class="grid h-10 w-10 place-items-center rounded bg-[#fff8df] font-black text-[#0b1f3a]">I</span><h3 class="mt-3 font-black text-[#0b1f3a]">INVERSIONES</h3><p class="mt-1 text-sm text-slate-600">Consulta tu panel de accionista.</p><a class="mt-4 inline-block text-sm font-black underline" href="{{ route('investments.panel') }}">Abrir</a></article>
                    @endif
                </div>
            </section>
        </div>
    @endif
</x-layouts.app>
