<x-layouts.app title="Mi perfil">
    <div class="mx-auto max-w-6xl space-y-6">
        <header>
            <h2 class="text-2xl font-black text-[#0b1f3a]">MI PERFIL</h2>
            <p class="mt-1 text-slate-600">Consulta y actualiza tu información personal.</p>
        </header>

        @if($affiliate->status !== 'activo')
            <div class="rounded border border-amber-300 bg-amber-50 px-4 py-3 font-semibold text-amber-950">
                Tu afiliación se encuentra inactiva.
            </div>
        @endif

        <form method="post" action="{{ route('affiliate.profile.update') }}" enctype="multipart/form-data" class="space-y-6" data-profile-form>
            @csrf
            @method('PATCH')

            <section class="section-card">
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="text-lg font-black text-[#0b1f3a]">FOTOGRAFÍA</h3>
                    <p class="mt-1 text-sm text-slate-600">Esta fotografía también se utiliza en tu credencial institucional.</p>
                </div>
                <div class="mt-5 grid items-center gap-6 sm:grid-cols-[150px_1fr]">
                    <div class="mx-auto h-40 w-32 overflow-hidden rounded border-2 border-[#d4af37] bg-slate-100">
                        <img
                            src="{{ $affiliate->photo_path ? Storage::disk('public')->url($affiliate->photo_path) : '' }}"
                            alt="Fotografía actual"
                            class="{{ $affiliate->photo_path ? '' : 'hidden' }} h-full w-full object-cover"
                            data-photo-preview
                        >
                        <div class="{{ $affiliate->photo_path ? 'hidden' : '' }} grid h-full place-items-center px-3 text-center text-sm font-bold text-slate-500" data-photo-placeholder>
                            Sin fotografía
                        </div>
                    </div>
                    <div>
                        <input class="sr-only" id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-photo-input>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <label for="photo" class="btn-primary cursor-pointer">CAMBIAR FOTOGRAFÍA</label>
                            <button type="button" class="rounded border border-slate-300 bg-white px-4 py-2 font-bold text-slate-700 hover:bg-slate-50" data-photo-clear>
                                ELIMINAR SELECCIÓN
                            </button>
                        </div>
                        <p class="mt-3 text-sm text-slate-500">JPG, JPEG, PNG o WEBP. Tamaño máximo: 5 MB.</p>
                        @error('photo')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="section-card">
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="text-lg font-black text-[#0b1f3a]">DATOS INSTITUCIONALES</h3>
                    <p class="mt-1 text-sm text-slate-600">Estos datos forman parte de tu registro institucional. Para solicitar una corrección, comunícate con Secretaría.</p>
                </div>
                @php
                    $lockedFields = [
                        'Nombre completo' => $affiliate->full_name,
                        'Cédula de identidad' => $affiliate->ci,
                        'Código de afiliado' => $affiliate->registration_number ?: 'No registrado',
                        'Sector' => $affiliate->sector?->name ?: 'No registrado',
                        'Institución' => $affiliate->institution ?: $affiliate->sector?->institution ?: 'No registrada',
                        'Regional' => $affiliate->regional ?: $affiliate->sector?->regional ?: 'No registrada',
                        'Estado' => \App\Support\AffiliationStatusPresenter::label($affiliate->status),
                        'Fecha de afiliación' => $affiliate->created_at?->format('d/m/Y'),
                        'Tipo de afiliado' => $affiliate->plan?->name ?: 'No registrado',
                        'Usuario de acceso' => $affiliate->user?->email ?: 'No registrado',
                    ];
                @endphp
                <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($lockedFields as $label => $value)
                        <div class="rounded border border-slate-200 bg-slate-100 p-3">
                            <dt class="flex items-center gap-2 text-xs font-black uppercase text-slate-500">
                                {{ $label }}
                                <span class="relative inline-block h-3 w-3 border border-slate-500" aria-label="Dato bloqueado">
                                    <span class="absolute -top-2 left-0.5 h-2 w-2 rounded-t border border-b-0 border-slate-500"></span>
                                </span>
                            </dt>
                            <dd class="mt-2 break-words font-bold text-[#0b1f3a]">{{ $value ?: 'No registrado' }}</dd>
                            <p class="mt-1 text-xs text-slate-500">Dato institucional no editable</p>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="section-card">
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="text-lg font-black text-[#0b1f3a]">DATOS PERSONALES</h3>
                    <p class="mt-1 text-sm text-slate-600">Mantén actualizados tus datos de contacto.</p>
                </div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm font-bold text-slate-700">
                        Celular
                        <input class="form-input" name="phone" value="{{ old('phone', $affiliate->phone) }}" maxlength="30" autocomplete="tel">
                        @error('phone')<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="grid gap-2 text-sm font-bold text-slate-700">
                        Correo electrónico
                        <input class="form-input" type="email" name="email" value="{{ old('email', $affiliate->email) }}" maxlength="150" required autocomplete="email">
                        @error('email')<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="grid gap-2 text-sm font-bold text-slate-700 sm:col-span-2">
                        Dirección
                        <input class="form-input" name="address" value="{{ old('address', $affiliate->address) }}" maxlength="255" autocomplete="street-address">
                        @error('address')<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="grid gap-2 text-sm font-bold text-slate-700">
                        Fecha de nacimiento
                        <input class="form-input" type="date" name="birth_date" value="{{ old('birth_date', $affiliate->birth_date?->format('Y-m-d')) }}" max="{{ now()->subDay()->format('Y-m-d') }}">
                        @error('birth_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="grid gap-2 text-sm font-bold text-slate-700">
                        Estado civil
                        <select class="form-input" name="marital_status">
                            <option value="">No especificado</option>
                            @foreach(['Soltero/a', 'Casado/a', 'Divorciado/a', 'Viudo/a', 'Unión libre'] as $option)
                                <option value="{{ $option }}" @selected(old('marital_status', $affiliate->marital_status) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('marital_status')<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                </div>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="reset" class="rounded border border-slate-300 bg-white px-5 py-3 font-black text-slate-700 hover:bg-slate-50" data-profile-reset>
                        CANCELAR CAMBIOS
                    </button>
                    <button class="btn-primary px-5 py-3" type="submit">GUARDAR CAMBIOS</button>
                </div>
            </section>
        </form>

        <section class="section-card">
            <div class="border-b border-slate-200 pb-4">
                <h3 class="text-lg font-black text-[#0b1f3a]">MIS PAGOS</h3>
                <p class="mt-1 text-sm text-slate-600">Consulta los pagos registrados en tu cuenta.</p>
            </div>
            @if($payments->isEmpty())
                <p class="py-8 text-center font-semibold text-slate-500">No tienes pagos registrados.</p>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[850px] border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b-2 border-[#d4af37] text-xs uppercase text-slate-500">
                                <th class="p-3">Fecha</th>
                                <th class="p-3">Comprobante</th>
                                <th class="p-3">Concepto</th>
                                <th class="p-3">Método</th>
                                <th class="p-3 text-right">Monto</th>
                                <th class="p-3">Estado</th>
                                <th class="p-3">Observación</th>
                                <th class="p-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $payment)
                                @php($currency = $payment->plan?->currency ?: 'BOB')
                                <tr class="border-b border-slate-200 align-top">
                                    <td class="p-3">{{ ($payment->payment_date ?: $payment->submitted_at ?: $payment->created_at)?->format('d/m/Y') }}</td>
                                    <td class="p-3 font-bold">{{ $payment->transaction_number ?: 'Sin número' }}</td>
                                    <td class="p-3">{{ $payment->plan?->name ?: 'Afiliación' }}</td>
                                    <td class="p-3">{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?: 'No registrado')) }}</td>
                                    <td class="p-3 text-right font-black">{{ $currency }} {{ number_format($payment->paid_amount ?? $payment->amount, 2, ',', '.') }}</td>
                                    <td class="p-3"><x-affiliation-status :status="$payment->status" size="sm" /></td>
                                    <td class="max-w-52 p-3">{{ $payment->observations ?: $payment->rejection_reason ?: 'Sin observaciones' }}</td>
                                    <td class="p-3 text-right">
                                        @if($payment->voucher_path)
                                            <a class="font-black text-[#0b1f3a] underline" href="{{ route('affiliate.profile.payments.receipt', $payment) }}" target="_blank" rel="noopener">VER COMPROBANTE</a>
                                        @else
                                            <span class="text-slate-400">No disponible</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $payments->links() }}</div>
            @endif
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                const input = document.querySelector('[data-photo-input]');
                const preview = document.querySelector('[data-photo-preview]');
                const placeholder = document.querySelector('[data-photo-placeholder]');
                const initialSrc = preview?.getAttribute('src') || '';

                const restore = () => {
                    if (!input || !preview || !placeholder) return;
                    input.value = '';
                    preview.src = initialSrc;
                    preview.classList.toggle('hidden', !initialSrc);
                    placeholder.classList.toggle('hidden', Boolean(initialSrc));
                };

                input?.addEventListener('change', () => {
                    const file = input.files?.[0];
                    if (!file || !file.type.startsWith('image/')) return restore();
                    preview.src = URL.createObjectURL(file);
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                });
                document.querySelector('[data-photo-clear]')?.addEventListener('click', restore);
                document.querySelector('[data-profile-reset]')?.addEventListener('click', () => requestAnimationFrame(restore));
            })();
        </script>
    @endpush
</x-layouts.app>
