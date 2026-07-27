@php
    session()->forget('status');
@endphp

<x-layouts.app title="Solicitud recibida">
    <div class="flex min-h-[calc(100vh-3rem)] items-center justify-center py-3 sm:py-8">
        <section class="w-full max-w-[680px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
            <div class="h-2 bg-[#0b1f3a]"></div>

            <div class="px-5 py-7 text-center sm:px-10 sm:py-10">
                <div class="relative mx-auto h-[72px] w-[72px] rounded-full border border-[#d4af37]/50 bg-[#fff8df]" aria-hidden="true">
                    <span class="absolute left-1/2 top-1/2 h-9 w-9 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[#0b1f3a]"></span>
                    <span class="absolute left-1/2 top-[22px] h-[15px] w-[3px] -translate-x-1/2 rounded bg-[#0b1f3a]"></span>
                    <span class="absolute left-1/2 top-1/2 h-[3px] w-[11px] origin-left -translate-y-1/2 rotate-[28deg] rounded bg-[#0b1f3a]"></span>
                </div>

                <h1 class="mt-6 text-2xl font-black uppercase text-[#0b1f3a] sm:text-3xl">
                    Pago enviado a revisión
                </h1>
                <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600 sm:text-base">
                    Tu comprobante fue recibido correctamente.<br class="hidden sm:block">
                    La activación de tu afiliación no es automática y será revisada por Secretaría.
                </p>

                <div class="mt-7">
                    <p class="text-xs font-black uppercase text-slate-500">Código de seguimiento</p>
                    <div class="mt-2 rounded-lg border-2 border-[#d4af37] bg-[#fff8df] px-4 py-5 sm:px-7">
                        <p class="break-all text-[clamp(1.5rem,7vw,2.25rem)] font-extrabold leading-tight text-[#0b1f3a]" data-tracking-code>{{ $application->request_code }}</p>
                        <button
                            class="mt-4 inline-flex min-h-10 items-center justify-center rounded border border-[#0b1f3a]/20 bg-white px-4 py-2 text-xs font-black uppercase text-[#0b1f3a] transition hover:border-[#d4af37] hover:bg-[#0b1f3a] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#d4af37]"
                            type="button"
                            data-copy-code
                            aria-label="Copiar código de seguimiento"
                        >
                            Copiar código
                        </button>
                    </div>
                    <p class="mt-3 text-sm text-slate-600">
                        Guarda este código. Lo necesitarás para consultar el estado de tu solicitud.
                    </p>
                </div>

                <section class="mx-auto mt-7 max-w-lg border-t border-slate-200 pt-7 text-left">
                    <h2 class="text-center text-sm font-black uppercase text-[#0b1f3a]">Datos de acceso</h2>
                    <div class="mt-4 grid gap-4">
                        <div>
                            <p class="form-label">Usuario</p>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <code class="min-w-0 flex-1 break-all rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm" data-access-user>{{ $application->person->email }}</code>
                                <button class="btn-secondary shrink-0" type="button" data-copy-access="user">Copiar usuario</button>
                            </div>
                        </div>
                        <div>
                            <p class="form-label">Contraseña</p>
                            @if($temporaryPassword)
                                <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                                    <x-password-input name="temporary_password" label="" :value="$temporaryPassword" :required="false" autocomplete="off" readonly />
                                    <button class="btn-secondary" type="button" data-copy-access="password">Copiar contraseña</button>
                                </div>
                                <p class="mt-2 text-xs text-amber-800">Guárdala ahora. Por seguridad solo se mostrará una vez.</p>
                            @else
                                <p class="rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">Por seguridad, la contraseña solo se muestra una vez.</p>
                            @endif
                        </div>
                    </div>
                    <a class="btn-secondary mt-5 min-h-12 w-full uppercase" href="{{ auth()->check() ? route('affiliate.panel') : route('login') }}">Ir a mi panel</a>
                </section>

                <ol class="mx-auto mt-8 grid max-w-lg gap-0 text-left" aria-label="Progreso de la afiliación">
                    @foreach([
                        ['Solicitud registrada', 'completed'],
                        ['Pago enviado', 'completed'],
                        ['Revisión de Secretaría', 'current'],
                        ['Activación y credencial', 'pending'],
                    ] as [$label, $state])
                        <li class="relative flex min-h-14 items-start gap-4 pb-3">
                            @if(!$loop->last)
                                <span class="absolute left-[15px] top-8 h-[calc(100%-1rem)] w-0.5 {{ $state === 'completed' ? 'bg-[#d4af37]' : 'bg-slate-200' }}" aria-hidden="true"></span>
                            @endif
                            <span class="relative z-10 grid h-8 w-8 shrink-0 place-items-center rounded-full border-2 text-sm font-black
                                {{ $state === 'completed' ? 'border-[#d4af37] bg-[#d4af37] text-[#0b1f3a]' : '' }}
                                {{ $state === 'current' ? 'border-[#0b1f3a] bg-[#0b1f3a] text-[#d4af37]' : '' }}
                                {{ $state === 'pending' ? 'border-slate-300 bg-white text-slate-400' : '' }}">
                                {{ $state === 'completed' ? '✓' : ($state === 'current' ? '●' : '○') }}
                            </span>
                            <span class="pt-1 font-bold {{ $state === 'pending' ? 'text-slate-400' : 'text-[#0b1f3a]' }}">{{ $label }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="mx-auto mt-5 grid max-w-md gap-3">
                    <a class="btn-primary min-h-12 w-full uppercase" href="{{ route('public-affiliation.status', $application) }}">
                        Consultar estado
                    </a>
                    <a class="btn-secondary min-h-12 w-full uppercase" href="{{ route('public-affiliation.index') }}">
                        Volver al inicio
                    </a>
                </div>
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            const copyButton = document.querySelector('[data-copy-code]');
            const trackingCode = document.querySelector('[data-tracking-code]');

            copyButton?.addEventListener('click', async () => {
                const code = trackingCode?.textContent.trim() ?? '';
                const originalLabel = copyButton.textContent.trim();

                try {
                    await navigator.clipboard.writeText(code);
                } catch {
                    const input = document.createElement('textarea');
                    input.value = code;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    input.remove();
                }

                copyButton.textContent = 'Código copiado';
                window.setTimeout(() => {
                    copyButton.textContent = originalLabel;
                }, 2000);
            });

            document.querySelectorAll('[data-copy-access]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const source = button.dataset.copyAccess === 'user'
                        ? document.querySelector('[data-access-user]')
                        : document.querySelector('[name="temporary_password"]');
                    const value = source?.value ?? source?.textContent.trim() ?? '';
                    if (!value) return;
                    await navigator.clipboard.writeText(value);
                    const label = button.textContent;
                    button.textContent = 'Copiado';
                    window.setTimeout(() => button.textContent = label, 2000);
                });
            });
        </script>
    @endpush
</x-layouts.app>
