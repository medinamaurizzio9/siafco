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
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="text-slate-600">{{ $affiliate->sector->name }}</span>
                        <x-affiliation-status :status="$affiliate->status" size="sm" />
                    </div>
                    @if(auth()->user()->hasPermission('affiliates.update'))
                        <a class="btn-secondary mt-4" href="{{ route('affiliates.edit', $affiliate) }}">Editar datos</a>
                    @endif
                </div>
            </div>
            <dl class="mt-6 grid gap-4 md:grid-cols-2">
                @foreach(['CI' => $affiliate->ci, 'Correo' => $affiliate->email, 'Celular' => $affiliate->phone, 'Regional' => $affiliate->regional, 'Institucion' => $affiliate->institution, 'Cargo/profesion' => $affiliate->position, 'Tipo de afiliado' => $affiliate->affiliate_type, 'Direccion' => $affiliate->address, 'Fecha de nacimiento' => $affiliate->birth_date?->format('d/m/Y'), 'Estado civil' => $affiliate->marital_status] as $label => $value)
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
                <p class="mt-3 text-sm font-bold text-slate-700">Estado: {{ $affiliate->credential?->status === 'suspendida' ? 'Suspendida' : 'Vigente' }}</p>
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
            @if(auth()->user()->hasPermission('affiliates.manage_credential'))
                <div class="mt-4 grid gap-2">
                    <form method="post" action="{{ route('admin.affiliates.credential.regenerate', $affiliate) }}">@csrf<button class="btn-secondary w-full">Regenerar archivos</button></form>
                    @if($affiliate->credential?->status === 'suspendida')
                        <form method="post" action="{{ route('admin.affiliates.credential.reactivate', $affiliate) }}">@csrf<button class="btn-primary w-full">Reactivar credencial</button></form>
                    @else
                        <form method="post" action="{{ route('admin.affiliates.credential.suspend', $affiliate) }}">@csrf<input class="form-input mb-2" name="reason" placeholder="Motivo de suspension" required><button class="btn-danger w-full">Suspender credencial</button></form>
                    @endif
                </div>
            @endif
        </aside>
    </div>

    <div class="mt-6 grid gap-5 xl:grid-cols-2">
        @can('updatePersonal', $affiliate)
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <p class="text-xs font-black uppercase text-[#b8942f]">Datos personales</p>
                <form class="mt-4 grid gap-3 md:grid-cols-2" method="post" action="{{ route('admin.affiliates.personal.update', $affiliate) }}">
                    @csrf @method('patch')
                    <label class="grid gap-1 text-sm font-bold">Celular<input class="form-input" name="phone" value="{{ old('phone', $affiliate->phone) }}"></label>
                    <label class="grid gap-1 text-sm font-bold">Correo<input class="form-input" type="email" name="email" value="{{ old('email', $affiliate->email) }}" required></label>
                    <label class="grid gap-1 text-sm font-bold md:col-span-2">Direccion<input class="form-input" name="address" value="{{ old('address', $affiliate->address) }}"></label>
                    <label class="grid gap-1 text-sm font-bold">Fecha de nacimiento<input class="form-input" type="date" name="birth_date" value="{{ old('birth_date', $affiliate->birth_date?->toDateString()) }}"></label>
                    <label class="grid gap-1 text-sm font-bold">Estado civil<input class="form-input" name="marital_status" value="{{ old('marital_status', $affiliate->marital_status) }}"></label>
                    <div class="md:col-span-2"><button class="btn-primary">Guardar datos personales</button></div>
                </form>
            </section>
        @endcan

        @if(auth()->user()->hasPermission('affiliates.manage_photo'))
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <p class="text-xs font-black uppercase text-[#b8942f]">Fotografia</p>
                <form class="mt-4 grid gap-3" method="post" enctype="multipart/form-data" action="{{ route('admin.affiliates.photo.update', $affiliate) }}">
                    @csrf
                    <input class="form-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                    <p class="text-sm text-slate-600">Se procesa como imagen cuadrada optimizada. No se usan nombres originales.</p>
                    <button class="btn-primary">Actualizar fotografia</button>
                </form>
            </section>
        @endif

        @can('updateInstitutional', $affiliate)
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <p class="text-xs font-black uppercase text-[#b8942f]">Datos institucionales</p>
                <form class="mt-4 grid gap-3 md:grid-cols-2" method="post" action="{{ route('admin.affiliates.institutional.update', $affiliate) }}">
                    @csrf @method('patch')
                    <label class="grid gap-1 text-sm font-bold">Regional<input class="form-input" name="regional" value="{{ old('regional', $affiliate->regional) }}"></label>
                    <label class="grid gap-1 text-sm font-bold">Institucion<input class="form-input" name="institution" value="{{ old('institution', $affiliate->institution) }}"></label>
                    <label class="grid gap-1 text-sm font-bold">Cargo/profesion<input class="form-input" name="position" value="{{ old('position', $affiliate->position) }}"></label>
                    <label class="grid gap-1 text-sm font-bold">Tipo de afiliado<input class="form-input" name="affiliate_type" value="{{ old('affiliate_type', $affiliate->affiliate_type) }}"></label>
                    <label class="grid gap-1 text-sm font-bold md:col-span-2">Observaciones administrativas<textarea class="form-input" name="administrative_notes" rows="3">{{ old('administrative_notes', $affiliate->administrative_notes) }}</textarea></label>
                    <div class="md:col-span-2"><button class="btn-primary">Guardar datos institucionales</button></div>
                </form>
            </section>
        @endcan

        @if(auth()->user()->hasPermission('affiliates.change_sector') || auth()->user()->hasPermission('affiliates.change_plan') || auth()->user()->hasPermission('affiliates.change_status'))
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <p class="text-xs font-black uppercase text-[#b8942f]">Sector, plan y estado</p>
                <div class="mt-4 grid gap-4">
                    @if(auth()->user()->hasPermission('affiliates.change_sector'))
                        <form class="grid gap-2" method="post" action="{{ route('admin.affiliates.sector.update', $affiliate) }}">
                            @csrf @method('patch')
                            <label class="text-sm font-bold">Sector<select class="form-input" name="sector_id">@foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected($affiliate->sector_id === $sector->id)>{{ $sector->name }}</option>@endforeach</select></label>
                            <button class="btn-secondary">Cambiar sector</button>
                        </form>
                    @endif
                    @if(auth()->user()->hasPermission('affiliates.change_plan'))
                        <form class="grid gap-2" method="post" action="{{ route('admin.affiliates.plan.update', $affiliate) }}">
                            @csrf @method('patch')
                            <label class="text-sm font-bold">Plan<select class="form-input" name="affiliation_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($affiliate->affiliation_plan_id === $plan->id)>{{ $plan->name }} - {{ $plan->currency ?? 'BOB' }} {{ number_format($plan->total_amount, 2) }}</option>@endforeach</select></label>
                            <button class="btn-secondary">Cambiar plan</button>
                        </form>
                    @endif
                    @if(auth()->user()->hasPermission('affiliates.change_status'))
                        <form class="grid gap-2" method="post" action="{{ route('admin.affiliates.status.update', $affiliate) }}">
                            @csrf @method('patch')
                            <label class="text-sm font-bold">Accion<select class="form-input" name="action"><option value="activate">Activar</option><option value="suspend">Suspender</option><option value="deactivate">Dar de baja</option><option value="reactivate">Reactivar</option></select></label>
                            <label class="text-sm font-bold">Motivo<input class="form-input" name="reason" placeholder="Obligatorio para suspension o baja"></label>
                            <button class="btn-primary">Aplicar estado</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </div>

    @can('viewAccess', $affiliate)
        @php($accessUser = $affiliate->user)
        <section class="mt-6 rounded-lg border border-amber-200 bg-white p-5">
            <p class="text-xs font-black uppercase text-amber-700">Cuenta de acceso</p>
            <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="font-black text-[#0b1f3a]">Portal web y aplicacion movil</h3>
                    <p class="mt-1 text-sm text-slate-600">El identificador principal de inicio de sesion del afiliado es su correo electronico.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('blockAccess', $affiliate)
                        <form method="post" action="{{ route('admin.affiliates.access.block', $affiliate) }}">@csrf<button class="btn-danger">BLOQUEAR ACCESO</button></form>
                    @endcan
                    @can('activateAccess', $affiliate)
                        <form method="post" action="{{ route('admin.affiliates.access.activate', $affiliate) }}">@csrf<button class="btn-primary">ACTIVAR ACCESO</button></form>
                    @endcan
                    @can('revokeSessions', $affiliate)
                        <form method="post" action="{{ route('admin.affiliates.access.revoke-sessions', $affiliate) }}">@csrf<button class="btn-secondary">CERRAR SESIONES</button></form>
                    @endcan
                    @can('resetPassword', $affiliate)
                        <button class="rounded border border-amber-400 bg-amber-50 px-4 py-2 font-black text-amber-950" type="button" data-password-reset-open>RESTABLECER CONTRASENA</button>
                    @endcan
                </div>
            </div>

            <dl class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div><dt class="text-xs font-black uppercase text-slate-500">Estado de afiliacion</dt><dd class="mt-1"><x-affiliation-status :status="$affiliate->status" size="sm" /></dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Estado de la cuenta</dt><dd class="mt-1"><span class="badge {{ $accessUser?->is_active ? '!bg-emerald-100 !text-emerald-800' : '!bg-red-100 !text-red-800' }}">{{ $accessUser ? ($accessUser->is_active ? 'Activa' : 'Bloqueada') : 'Sin cuenta vinculada' }}</span></dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Correo de acceso</dt><dd class="mt-1 break-all font-bold text-slate-900">{{ $accessUser?->email ?? $affiliate->email ?? 'Sin correo' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Usuario compatible</dt><dd class="mt-1 break-all font-bold text-slate-900">{{ $accessUser?->username ?: 'Generado internamente' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Cambio obligatorio</dt><dd class="mt-1 font-bold text-slate-900">{{ $accessUser?->must_change_password ? 'Pendiente' : 'No pendiente' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Ultimo acceso</dt><dd class="mt-1 font-bold text-slate-900">{{ $accessUser?->last_login_at?->format('d/m/Y H:i') ?? 'Nunca ingreso' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Ultima IP</dt><dd class="mt-1 font-bold text-slate-900">{{ $accessUser?->last_login_ip ?: 'No registrada' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Acceso movil</dt><dd class="mt-1 font-bold text-slate-900">{{ $accessUser?->is_active ? 'Habilitado' : 'Requiere activacion' }}</dd></div>
            </dl>

            @unless($accessUser)
                <p class="mt-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">Este afiliado no tiene una cuenta de acceso vinculada. La ficha se muestra sin error para permitir revision administrativa.</p>
            @endunless
        </section>

        @can('resetPassword', $affiliate)
            <dialog class="w-[min(92vw,520px)] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/70" data-password-reset-dialog>
                <form method="post" action="{{ route('admin.affiliates.password.reset', $affiliate) }}" class="p-5">
                    @csrf
                    <h2 class="text-xl font-black text-[#0b1f3a]">RESTABLECER CONTRASENA</h2>
                    <p class="mt-3 text-sm text-slate-600">La contrasena temporal corresponde al CI del afiliado. Debera cambiarla al ingresar.</p>
                    <dl class="mt-4 grid gap-2 rounded bg-slate-50 p-4 text-sm">
                        <div><dt class="font-bold text-slate-500">Afiliado</dt><dd>{{ $affiliate->full_name }}</dd></div>
                        <div><dt class="font-bold text-slate-500">CI</dt><dd>{{ $affiliate->ci }}</dd></div>
                        <div><dt class="font-bold text-slate-500">Codigo</dt><dd>{{ $affiliate->registration_number }}</dd></div>
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
    @endcan

    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase text-[#b8942f]">Tesoreria del afiliado</p>
                <h3 class="text-xl font-black text-[#0b1f3a]">Resumen de pagos</h3>
            </div>
            @if(auth()->user()->hasPermission('payments.create'))
                <a class="btn-primary" href="{{ route('payments.create', ['affiliate_id' => $affiliate->id]) }}">Registrar pago</a>
            @endif
        </div>
        <dl class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-xs font-black uppercase text-slate-500">Monto total del plan</dt><dd class="font-bold">BOB {{ number_format($treasury['required_amount'], 2) }}</dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Total confirmado</dt><dd class="font-bold">BOB {{ number_format($treasury['confirmed_amount'], 2) }}</dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Saldo pendiente</dt><dd class="font-bold">BOB {{ number_format($treasury['pending_balance'], 2) }}</dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Cantidad de pagos</dt><dd class="font-bold">{{ $treasury['payment_count'] }}</dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Ultimo pago</dt><dd class="font-bold">{{ $treasury['latest_payment']?->paid_at?->format('d/m/Y H:i') ?? $treasury['latest_payment']?->created_at?->format('d/m/Y H:i') ?? 'Sin pagos' }}</dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Estado del pago</dt><dd class="font-bold">{{ \App\Support\PaymentStatus::label($treasury['payment_status']) }}</dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Estado de afiliacion</dt><dd><x-affiliation-status :status="$affiliate->status" size="sm" /></dd></div>
            <div><dt class="text-xs font-black uppercase text-slate-500">Credencial</dt><dd class="font-bold">{{ $treasury['credential_status'] === 'generada' ? 'Generada' : 'No generada' }}</dd></div>
        </dl>
    </section>

    <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white" id="payments">
        <div class="border-b border-slate-200 px-4 py-3"><h3 class="font-black text-[#0b1f3a]">Pagos</h3></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Monto</th><th>Transaccion</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                @foreach($affiliate->payments as $payment)
                    <tr>
                        <td>{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</td>
                        <td>{{ $payment->transaction_number ?: 'Pendiente' }}</td>
                        <td><x-affiliation-status :status="$payment->status" size="sm" /></td>
                        <td>
                            <a class="btn-secondary" href="{{ route('payments.show', $payment) }}">Ver</a>
                            @if(auth()->user()->hasPermission('payments.confirm') && \App\Support\PaymentStatus::isEditable($payment->status))
                                <form class="inline" method="post" action="{{ route('payments.confirm', $payment) }}">@csrf<button class="btn-primary">Confirmar</button></form>
                            @endif
                            @if(auth()->user()->hasPermission('payments.reject') && ! \App\Support\PaymentStatus::isConfirmed($payment->status) && ! \App\Support\PaymentStatus::isVoided($payment->status))
                                <form class="mt-2 flex gap-2" method="post" action="{{ route('payments.reject', $payment) }}">@csrf<input class="form-input" name="rejection_reason" placeholder="Motivo"><button class="btn-danger">Rechazar</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @php($duplicateCount = collect($duplicates)->sum(fn ($items) => $items->count()))
    @if($duplicateCount > 0)
        <section class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs font-black uppercase text-amber-800">Posible duplicado</p>
            <h3 class="mt-1 font-black text-amber-950">Registros para revisar manualmente</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                @foreach($duplicates as $type => $items)
                    @foreach($items as $person)
                        <div class="rounded border border-amber-200 bg-white p-3 text-sm">
                            <p class="font-black">{{ str($type)->replace('_', ' ')->headline() }}</p>
                            <p>{{ $person->full_name }} · CI {{ $person->ci ?: 'sin dato' }}</p>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif

    @if(auth()->user()->hasPermission('affiliates.view_timeline'))
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-xs font-black uppercase text-[#b8942f]">Timeline</p>
            <div class="mt-4 grid gap-3">
                @forelse($timeline as $event)
                    <div class="rounded border border-slate-200 p-3">
                        <p class="font-black text-[#0b1f3a]">{{ $event['label'] }}</p>
                        <p class="text-sm text-slate-600">{{ $event['occurred_at']?->format('d/m/Y H:i') }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-600">Sin eventos auditados.</p>
                @endforelse
            </div>
        </section>
    @endif

    @if(auth()->user()->hasPermission('affiliates.view_audit'))
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-xs font-black uppercase text-[#b8942f]">Auditoria</p>
            <div class="mt-4 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Accion</th><th>Fecha</th><th>Detalle</th></tr></thead>
                    <tbody>
                    @forelse($auditLogs as $log)
                        <tr>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="max-w-lg truncate">{{ json_encode(collect($log->metadata ?? [])->except(['password', 'token', 'verification_token', 'qr'])->all(), JSON_UNESCAPED_UNICODE) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Sin registros de auditoria.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.app>
