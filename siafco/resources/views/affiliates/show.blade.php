<x-layouts.app title="{{ $affiliate->full_name }}">
    <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-col gap-4 sm:flex-row">
                <div class="grid h-28 w-28 shrink-0 place-items-center overflow-hidden rounded bg-slate-100">
                    @if($affiliate->photo_path)
                        <img class="h-full w-full object-cover" src="{{ Storage::url($affiliate->photo_path) }}" alt="">
                    @else
                        <span class="text-3xl font-black text-[#0b1f3a]">{{ substr($affiliate->full_name, 0, 1) }}</span>
                    @endif
                </div>
                <div>
                    <p class="text-sm font-bold uppercase text-[#b8942f]">{{ $affiliate->registration_number }}</p>
                    <h2 class="text-3xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2"><span class="text-slate-600">{{ $affiliate->sector->name }}</span><x-affiliation-status :status="$affiliate->status" size="sm" /></div>
                    @if(auth()->user()->hasRole(['administrador','administrador_sector','secretaria']))
                        <a class="btn-secondary mt-4" href="{{ route('affiliates.edit', $affiliate) }}">Editar datos</a>
                    @endif
                </div>
            </div>
            <dl class="mt-6 grid gap-4 md:grid-cols-2">
                @foreach(['CI' => $affiliate->ci, 'Correo' => $affiliate->email, 'Celular' => $affiliate->phone, 'Regional' => $affiliate->regional, 'Institucion' => $affiliate->institution, 'Cargo/profesion' => $affiliate->position, 'Direccion' => $affiliate->address, 'Estado civil' => $affiliate->marital_status] as $label => $value)
                    <div><dt class="text-xs font-black uppercase text-slate-500">{{ $label }}</dt><dd>{{ $value ?: 'Sin dato' }}</dd></div>
                @endforeach
            </dl>
        </section>

        <aside class="rounded-lg border border-slate-200 bg-white p-5">
            <h3 class="font-black text-[#0b1f3a]">Credencial digital</h3>
            @if($affiliate->status !== 'activo')
                <p class="mt-3 rounded border border-[#d4af37]/40 bg-[#fff8df] p-3 text-sm text-slate-900">Debe confirmar su pago para habilitar su credencial digital.</p>
            @elseif($affiliate->credential)
                <div class="mt-4 rounded border border-slate-200 bg-slate-50 p-3">
                    <div class="mx-auto grid aspect-[850/540] w-40 place-items-center rounded border border-[#d4af37]/50 bg-white text-center text-xs font-black text-[#0b1f3a] shadow">
                        CREDENCIAL DIGITAL
                    </div>
                </div>
                @can('viewCredential', $affiliate)
                    <a class="btn-primary mt-4 w-full" href="{{ route('credenciales.show', $affiliate) }}">Ver credencial</a>
                @endcan
                @can('downloadCredential', $affiliate)
                    <a class="btn-secondary mt-3 w-full" href="{{ route('credentials.pdf', $affiliate) }}">Descargar PDF</a>
                @endcan
                @can('printCredential', $affiliate)
                    <a class="btn-secondary mt-3 w-full" href="{{ route('credentials.print', $affiliate) }}" target="_blank">Imprimir</a>
                @endcan
                <a class="btn-secondary mt-3 w-full" href="{{ route('verify.show', $affiliate->verification_token) }}" target="_blank">Verificar publico</a>
            @else
                @if(auth()->user()->hasRole(['administrador','secretaria']))
                    <a class="btn-primary mt-4 w-full" href="{{ route('credenciales.show', $affiliate) }}">Ver credencial completa</a>
                @endif
            @endif
        </aside>
    </div>

    @can('resetPassword', $affiliate)
        <section class="mt-6 rounded-lg border border-amber-200 bg-white p-5">
            <p class="text-xs font-black uppercase text-amber-700">Seguridad</p>
            <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h3 class="font-black text-[#0b1f3a]">Cuenta de acceso</h3><p class="mt-1 text-sm text-slate-600">Restablece el acceso únicamente cuando el afiliado lo solicite.</p></div>
                <button class="rounded border border-amber-400 bg-amber-50 px-4 py-2 font-black text-amber-950" type="button" data-password-reset-open>RESTABLECER CONTRASEÑA</button>
            </div>
        </section>
        <dialog class="w-[min(92vw,520px)] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/70" data-password-reset-dialog>
            <form method="post" action="{{ route('admin.affiliates.password.reset', $affiliate) }}" class="p-5">
                @csrf
                <h2 class="text-xl font-black text-[#0b1f3a]">RESTABLECER CONTRASEÑA</h2>
                <p class="mt-3 text-sm text-slate-600">La contraseña temporal será el número de carnet del afiliado. Al iniciar sesión deberá cambiarla obligatoriamente.</p>
                <dl class="mt-4 grid gap-2 rounded bg-slate-50 p-4 text-sm">
                    <div><dt class="font-bold text-slate-500">Afiliado</dt><dd>{{ $affiliate->full_name }}</dd></div>
                    <div><dt class="font-bold text-slate-500">CI</dt><dd>{{ $affiliate->ci }}</dd></div>
                    <div><dt class="font-bold text-slate-500">Código</dt><dd>{{ $affiliate->registration_number }}</dd></div>
                </dl>
                <label class="mt-4 grid gap-2 text-sm font-bold text-slate-700">Escribe RESTABLECER para confirmar
                    <input class="form-input" name="confirmation" autocomplete="off" required data-password-reset-confirmation>
                </label>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button class="btn-secondary" type="button" data-password-reset-close>CANCELAR</button>
                    <button class="rounded bg-amber-400 px-4 py-2 font-black text-[#0b1f3a] disabled:opacity-50" type="submit" disabled data-password-reset-submit>CONFIRMAR RESTABLECIMIENTO</button>
                </div>
            </form>
        </dialog>
    @endcan

    <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3"><h3 class="font-black text-[#0b1f3a]">Pagos</h3></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Monto</th><th>Transaccion</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                @foreach($affiliate->payments as $payment)
                    <tr>
                        <td>Bs {{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $payment->transaction_number ?: 'Pendiente' }}</td>
                        <td><x-affiliation-status :status="$payment->status" size="sm" /></td>
                        <td>
                            @if(auth()->user()->hasRole(['administrador','cajero']))
                                <form class="inline" method="post" action="{{ route('payments.confirm', $payment) }}">@csrf<button class="btn-primary">Confirmar</button></form>
                                <form class="mt-2 flex gap-2" method="post" action="{{ route('payments.reject', $payment) }}">@csrf<input class="form-input" name="rejection_reason" placeholder="Motivo"><button class="btn-danger">Rechazar</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
