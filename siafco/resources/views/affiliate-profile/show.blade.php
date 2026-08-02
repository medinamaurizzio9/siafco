<x-layouts.app title="Mi perfil">
    <div class="mx-auto max-w-6xl space-y-6">
        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-[72px_1fr_auto] sm:items-center">
                <div class="mx-auto h-16 w-16 overflow-hidden rounded-full border-2 border-[#d4af37] bg-slate-100">
                    @if($affiliate->photo_path)<img class="h-full w-full object-cover" src="{{ Storage::disk('public')->url($affiliate->photo_path) }}" alt="Fotografía de {{ $affiliate->full_name }}">@else<div class="grid h-full place-items-center font-black text-slate-400">{{ mb_substr($affiliate->full_name, 0, 1) }}</div>@endif
                </div>
                <div class="min-w-0 text-center sm:text-left">
                    <h2 class="break-words text-2xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h2>
                    <p class="mt-1 font-bold text-[#b8942f]">{{ $affiliate->registration_number }}</p>
                    <p class="mt-2 text-sm text-slate-600">Gestiona tu información personal y consulta tus datos institucionales.</p>
                </div>
                <div class="text-center sm:text-right">
                    <x-affiliation-status :status="$affiliate->status" size="sm" />
                    <p class="mt-2 text-xs text-slate-500">Actualizado {{ $affiliate->updated_at->format('d/m/Y') }}</p>
                    <a class="btn-secondary mt-3 w-full sm:w-auto" href="{{ route('affiliate.panel') }}">VOLVER AL PANEL</a>
                </div>
            </div>
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
                <x-forms.photo-cropper
                    :required="false"
                    :initial-src="$affiliate->photo_path ? Storage::disk('public')->url($affiliate->photo_path) : null"
                    label="FOTOGRAFÍA INSTITUCIONAL"
                    description="Esta imagen también se utilizará en tu credencial digital."
                    select-label="CAMBIAR FOTOGRAFÍA"
                    cancel-label="CANCELAR CAMBIO"
                />
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
                <p class="mt-4 rounded bg-slate-50 px-4 py-3 text-sm text-slate-600">Los siguientes datos están protegidos y solo pueden ser corregidos por Secretaría.</p>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($lockedFields as $label => $value)
                        <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-3 shadow-sm {{ $label === 'Usuario de acceso' ? 'lg:col-span-3' : '' }}">
                            <dt class="flex items-center gap-2 text-xs font-black uppercase text-slate-500">
                                {{ $label }}
                                <span class="relative inline-block h-3 w-3 border border-slate-500" aria-label="Dato bloqueado">
                                    <span class="absolute -top-2 left-0.5 h-2 w-2 rounded-t border border-b-0 border-slate-500"></span>
                                </span>
                            </dt>
                            <dd class="mt-2 break-words font-semibold leading-[1.35] text-[#0b1f3a]">{{ $value ?: 'No registrado' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="section-card">
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="text-lg font-black text-[#0b1f3a]">DATOS PERSONALES</h3>
                    <p class="mt-1 text-sm text-slate-600">Mantén actualizados tus datos de contacto.</p>
                </div>
                <h4 class="mt-5 text-xs font-black uppercase text-[#b8942f]">Contacto</h4>
                <div class="mt-3 grid gap-5 sm:grid-cols-2">
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
                </div>
                <h4 class="mt-6 text-xs font-black uppercase text-[#b8942f]">Información personal</h4>
                <div class="mt-3 grid gap-5 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm font-bold text-slate-700">
                        Fecha de nacimiento
                        <input class="form-input" type="date" name="birth_date" value="{{ old('birth_date', $affiliate->birth_date?->format('Y-m-d')) }}" max="{{ now()->subDay()->format('Y-m-d') }}">
                        @error('birth_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="grid gap-2 text-sm font-bold text-slate-700">
                        Estado civil
                        <select class="form-input" name="marital_status">
                            <option value="">No especificado</option>
                            @foreach(\App\Support\PublicAffiliationCatalogs::MARITAL_STATUSES as $option)
                                <option value="{{ $option }}" @selected(old('marital_status', $affiliate->marital_status) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('marital_status')<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                </div>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="reset" class="rounded border border-slate-300 bg-white px-5 py-3 font-black text-slate-700 hover:bg-slate-50" data-profile-reset>
                        CANCELAR
                    </button>
                    <button class="btn-primary px-5 py-3" type="submit">GUARDAR CAMBIOS</button>
                </div>
            </section>
        </form>

        <section class="section-card">
            <div class="border-b border-slate-200 pb-4">
                <p class="text-xs font-black uppercase text-[#b8942f]">Cuenta de acceso</p>
                <h3 class="mt-1 text-lg font-black text-[#0b1f3a]">SEGURIDAD DE LA CUENTA</h3>
                <p class="mt-1 text-sm text-slate-600">Actualiza tu contraseña para mantener protegida tu cuenta.</p>
            </div>
            <form class="mt-5 grid gap-5 sm:grid-cols-2" method="post" action="{{ route('affiliate.profile.password.update') }}">
                @csrf
                @method('PATCH')
                <label class="grid gap-2 text-sm font-bold text-slate-700 sm:col-span-2">
                    Contraseña actual
                    <input class="form-input" type="password" name="current_password" required autocomplete="current-password">
                    @error('current_password')<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    Nueva contraseña
                    <input class="form-input" type="password" name="password" required minlength="8" autocomplete="new-password">
                    @error('password')<span class="text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    Confirmar nueva contraseña
                    <input class="form-input" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                </label>
                <div class="sm:col-span-2 sm:text-right">
                    <p class="mb-3 text-sm text-slate-500">Usa al menos 8 caracteres, incluyendo letras y números.</p>
                    <button class="btn-primary w-full py-3 sm:w-auto" type="submit">ACTUALIZAR CONTRASEÑA</button>
                </div>
            </form>
        </section>

        <section class="section-card" id="payments">
            <div class="border-b border-slate-200 pb-4">
                <h3 class="text-lg font-black text-[#0b1f3a]">MIS PAGOS</h3>
                <p class="mt-1 text-sm text-slate-600">Consulta los pagos registrados en tu cuenta.</p>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded bg-slate-50 p-3"><p class="text-xs font-bold uppercase text-slate-500">Total de pagos</p><p class="mt-1 text-xl font-black text-[#0b1f3a]">{{ $paymentSummary['count'] }}</p></div>
                <div class="rounded bg-slate-50 p-3"><p class="text-xs font-bold uppercase text-slate-500">Total acumulado</p><p class="mt-1 text-xl font-black text-[#0b1f3a]">BOB {{ number_format($paymentSummary['total'], 2, ',', '.') }}</p></div>
                <div class="rounded bg-slate-50 p-3"><p class="text-xs font-bold uppercase text-slate-500">Último pago</p><p class="mt-1 font-black text-[#0b1f3a]">{{ $paymentSummary['latest'] ? ($paymentSummary['latest']->payment_date ?: $paymentSummary['latest']->created_at)->format('d/m/Y') : 'Sin pagos' }}</p></div>
            </div>
            @if($payments->isEmpty())
                <p class="py-8 text-center font-semibold text-slate-500">No tienes pagos registrados.</p>
            @else
                <div class="mt-5 hidden overflow-x-auto md:block">
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
                <div class="mt-4 grid gap-3 md:hidden">
                    @foreach($payments as $payment)
                        @php($currency = $payment->plan?->currency ?: 'BOB')
                        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div><p class="text-xs font-bold uppercase text-slate-500">{{ ($payment->payment_date ?: $payment->submitted_at ?: $payment->created_at)?->format('d/m/Y') }}</p><h4 class="mt-1 font-black text-[#0b1f3a]">{{ $payment->plan?->name ?: 'Afiliación' }}</h4></div>
                                <x-affiliation-status :status="$payment->status" size="sm" />
                            </div>
                            <dl class="mt-3 grid gap-2 text-sm">
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Comprobante</dt><dd class="text-right font-semibold">{{ $payment->transaction_number ?: 'Sin número' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Monto</dt><dd class="font-black">{{ $currency }} {{ number_format($payment->paid_amount ?? $payment->amount, 2, ',', '.') }}</dd></div>
                            </dl>
                            @if($payment->voucher_path)<a class="mt-3 block text-center text-sm font-black text-[#0b1f3a] underline" href="{{ route('affiliate.profile.payments.receipt', $payment) }}" target="_blank" rel="noopener">VER COMPROBANTE</a>@endif
                        </article>
                    @endforeach
                </div>
                <div class="mt-5">{{ $payments->links() }}</div>
            @endif
        </section>
    </div>

</x-layouts.app>
