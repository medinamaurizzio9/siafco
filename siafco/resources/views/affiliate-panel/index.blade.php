<x-layouts.app title="Panel afiliado" :credential-assets="$isActive && (bool) $affiliate?->credential">
    @if(!$affiliate)
        <div class="section-card">No existe una ficha de afiliado vinculada a este usuario.</div>
    @elseif(!$isActive)
        @php($application = $affiliate->publicRequest)
        @php($currentStep = \App\Support\AffiliationStatusPresenter::currentStep($application?->status ?: $affiliate->status))
        <div class="mx-auto max-w-4xl space-y-6">
            <section class="section-card">
                <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div><p class="text-sm font-black uppercase text-[#b8942f]">{{ $application?->request_code ?: 'Solicitud en proceso' }}</p><h2 class="text-2xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h2></div>
                    <x-affiliation-status :status="$application?->status ?: $affiliate->status" size="sm" />
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-sm font-bold text-slate-500">Plan seleccionado</dt><dd class="font-bold">{{ $affiliate->plan->name }}</dd></div>
                    <div><dt class="text-sm font-bold text-slate-500">Monto</dt><dd class="font-bold">BOB {{ number_format($application?->amount_due ?? $latestPayment?->amount, 2) }}</dd></div>
                    <div><dt class="text-sm font-bold text-slate-500">Transacción</dt><dd>{{ $application?->payment?->transaction_number ?: 'Aún no registrada' }}</dd></div>
                    <div><dt class="text-sm font-bold text-slate-500">Comprobante</dt><dd>{{ $application?->payment?->voucher_path ? 'Enviado' : 'No enviado' }}</dd></div>
                </dl>
                @if($application?->rejection_reason || $application?->observations)
                    <div class="mt-5 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">{{ $application->rejection_reason ?: $application->observations }}</div>
                @endif
                <ol class="mt-7 grid gap-3 sm:grid-cols-4">
                    @foreach(['Solicitud registrada','Pago enviado','Revisión de Secretaría','Afiliación activa'] as $index => $step)
                        @php($number = $index + 1)
                        @php($done = $number < $currentStep || ($number === 2 && $currentStep >= 3))
                        @php($current = $number === $currentStep)
                        <li class="border-t-4 p-3 text-sm font-bold {{ $done ? 'border-[#d4af37] bg-[#fff8df]' : ($current ? 'border-[#0b1f3a] bg-slate-100' : 'border-slate-200 text-slate-400') }}">{{ $done ? '✓' : ($current ? '●' : '○') }} {{ $step }}</li>
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
        <div class="space-y-6">
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b-4 border-[#d4af37] bg-[#0b1f3a] px-5 py-4 text-white">
                    <p class="text-xs font-bold uppercase text-[#d4af37]">Bienvenido a SIAFCO</p>
                    <p class="mt-1 text-sm text-slate-300">Tu información institucional y servicios en un solo lugar.</p>
                </div>
                <div class="grid gap-5 p-5 md:grid-cols-[110px_1fr_auto] md:items-center">
                    <div class="mx-auto h-24 w-24 overflow-hidden rounded-full border-4 border-[#f7ecc4] bg-slate-100">
                        @if($affiliate->photo_path)<img class="h-full w-full object-cover" src="{{ Storage::disk('public')->url($affiliate->photo_path) }}" alt="Fotografía de {{ $affiliate->full_name }}">@else<div class="grid h-full place-items-center text-2xl font-black text-slate-400">{{ mb_substr($affiliate->full_name, 0, 1) }}</div>@endif
                    </div>
                    <div class="min-w-0 text-center md:text-left">
                        <h2 class="break-words text-2xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h2>
                        <p class="mt-1 font-bold text-[#b8942f]">{{ $affiliate->registration_number }}</p>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs font-bold uppercase text-slate-500">Sector</dt><dd class="mt-1 break-words font-semibold text-slate-800">{{ $affiliate->sector?->name }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-500">Regional</dt><dd class="mt-1 font-semibold text-slate-800">{{ $affiliate->regional ?: 'No registrada' }}</dd></div>
                        </dl>
                    </div>
                    <div class="text-center md:text-right">
                        <x-affiliation-status :status="$affiliate->status" size="sm" />
                        <p class="mt-3 text-xs font-bold uppercase text-slate-500">Fecha de afiliación</p>
                        <p class="mt-1 font-semibold text-slate-800">{{ $affiliate->created_at->format('d/m/Y') }}</p>
                        <a class="btn-primary mt-4 w-full md:w-auto" href="{{ route('affiliate.profile.show') }}">VER MI PERFIL</a>
                    </div>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-500">Afiliación</p><p class="mt-2 font-black text-[#0b1f3a]">{{ \App\Support\AffiliationStatusPresenter::label($affiliate->status) }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-500">Pagos registrados</p><p class="mt-2 text-2xl font-black text-[#0b1f3a]">{{ $affiliate->payments_count }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-500">Último pago</p><p class="mt-2 font-black text-[#0b1f3a]">{{ $latestPayment ? ($latestPayment->payment_date ?: $latestPayment->created_at)->format('d/m/Y') : 'Sin pagos registrados' }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-500">Credencial</p><p class="mt-2 font-black text-[#0b1f3a]">{{ $affiliate->credential ? 'Vigente' : 'En preparación' }}</p></div>
            </section>

            <section class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_390px]">
                <div>
                    <h2 class="text-xl font-black text-[#0b1f3a]">Mis servicios y beneficios</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <a class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-[#d4af37]" href="{{ route('affiliate.profile.show') }}"><p class="text-xs font-bold uppercase text-[#b8942f]">Cuenta</p><h3 class="mt-2 font-black text-[#0b1f3a]">Mi perfil</h3><p class="mt-1 text-sm text-slate-600">Actualiza tus datos personales y fotografía.</p></a>
                        @foreach($benefits as $benefit)
                            @php($href = $benefit->route_name && Route::has($benefit->route_name) ? route($benefit->route_name) : $benefit->external_url)
                            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-[#b8942f]">Servicio</p><h3 class="mt-2 font-black text-[#0b1f3a]">{{ $benefit->title }}</h3><p class="mt-1 text-sm text-slate-600">{{ $benefit->description }}</p>@if($href)<a class="mt-3 inline-block text-sm font-black text-[#0b1f3a] underline" href="{{ $href }}" @if($benefit->external_url) target="_blank" rel="noopener" @endif>Abrir</a>@else<span class="mt-3 inline-block text-xs font-black uppercase text-slate-400">Próximamente</span>@endif</article>
                        @endforeach
                    </div>
                </div>
                <aside class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between"><h2 class="font-black text-[#0b1f3a]">Credencial digital</h2><span class="text-xs font-bold uppercase text-emerald-700">{{ $affiliate->credential ? 'Vigente' : 'Pendiente' }}</span></div>
                    @if($affiliate->credential)
                        <div class="credential-canvas pointer-events-none mt-4" id="credential-canvas">
                            @include('credenciales.card', ['affiliate' => $affiliate, 'credential' => $affiliate->credential, 'credentialData' => $credentialData, 'institution' => $credentialInstitution, 'mode' => 'thumbnail'])
                        </div>
                        <a class="btn-primary mt-4 w-full" href="{{ route('affiliate.credential.preview') }}">VER MI CREDENCIAL</a>
                        <p class="mt-3 text-sm text-slate-600">Tu credencial digital se encuentra vigente y disponible para consulta.</p>
                    @else<p class="mt-5 rounded bg-slate-50 p-4 text-sm text-slate-600">La credencial se está preparando.</p>@endif
                </aside>
            </section>
        </div>
    @endif
</x-layouts.app>
