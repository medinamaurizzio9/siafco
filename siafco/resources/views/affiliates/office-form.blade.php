<x-layouts.app title="Afiliacion en oficina">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Nueva afiliacion presencial</h2>
            <p class="text-sm text-slate-600">Registro interno con cobro en efectivo recibido en oficina.</p>
        </div>
        <a class="btn-secondary" href="{{ route('affiliates.index') }}">Volver</a>
    </div>

    <form method="post" enctype="multipart/form-data" action="{{ route('affiliates.office.store') }}" class="grid gap-5">
        @csrf
        <section class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2 xl:grid-cols-3">
            <div class="xl:col-span-3">
                <h3 class="text-lg font-black text-[#0b1f3a]">Datos del afiliado</h3>
                <p class="text-sm text-slate-600">Use datos reales del afiliado atendido presencialmente.</p>
            </div>
            <div>
                <label class="form-label">Nombre completo</label>
                <input class="form-input" name="full_name" value="{{ old('full_name') }}" data-uppercase required>
                @error('full_name') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">CI</label>
                <input class="form-input" name="ci" value="{{ old('ci') }}" required>
                @error('ci') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Correo de acceso</label>
                <input class="form-input" type="email" name="email" value="{{ old('email') }}" required>
                @error('email') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Celular</label>
                <input class="form-input" name="phone" value="{{ old('phone') }}">
                @error('phone') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Sector</label>
                <select class="form-input" name="sector_id" required>
                    <option value="">Seleccione sector</option>
                    @foreach($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected(old('sector_id') == $sector->id)>{{ $sector->code }} - {{ $sector->name }}</option>
                    @endforeach
                </select>
                @error('sector_id') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Plan</label>
                <select class="form-input" name="affiliation_plan_id" data-office-plan required>
                    <option value="">Seleccione plan</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" data-amount="{{ number_format((float) $plan->total_amount, 2, '.', '') }}" data-currency="{{ $plan->currency ?? 'BOB' }}" @selected(old('affiliation_plan_id') == $plan->id)>
                            {{ $plan->name }} - {{ $plan->currency ?? 'BOB' }} {{ number_format((float) $plan->total_amount, 2) }}
                        </option>
                    @endforeach
                </select>
                @error('affiliation_plan_id') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Regional</label>
                <input class="form-input" name="regional" value="{{ old('regional') }}" data-uppercase>
                @error('regional') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Institucion <span class="text-xs font-medium text-slate-500">(opcional)</span></label>
                <input class="form-input" name="institution" value="{{ old('institution') }}" data-uppercase>
                @error('institution') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Cargo/profesion <span class="text-xs font-medium text-slate-500">(opcional)</span></label>
                <input class="form-input" name="position" value="{{ old('position') }}" data-uppercase>
                @error('position') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Fecha de nacimiento</label>
                <input class="form-input" type="date" name="birth_date" value="{{ old('birth_date') }}">
                @error('birth_date') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Estado civil</label>
                <input class="form-input" name="marital_status" value="{{ old('marital_status') }}" data-uppercase>
                @error('marital_status') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Fotografia</label>
                <input class="form-input" type="file" name="photo" accept="image/*">
                @error('photo') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div class="xl:col-span-3">
                <label class="form-label">Direccion</label>
                <input class="form-input" name="address" value="{{ old('address') }}" data-uppercase>
                @error('address') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="grid gap-4 rounded-lg border-2 border-siafco-gold-500 bg-white p-5 md:grid-cols-2 xl:grid-cols-3">
            <div class="xl:col-span-3">
                <h3 class="text-lg font-black text-[#0b1f3a]">Pago en oficina</h3>
                <p class="text-sm text-slate-600">El efectivo recibido presencialmente sera confirmado directamente por el sistema.</p>
            </div>
            <div>
                <label class="form-label">Metodo de pago</label>
                <input class="form-input bg-slate-100 font-bold" value="Efectivo / Pago en oficina" readonly>
            </div>
            <div>
                <label class="form-label">Monto del plan</label>
                <input class="form-input bg-slate-100 font-bold" value="Seleccione un plan" data-office-plan-total readonly>
            </div>
            <div>
                <label class="form-label">Cajero / usuario que recibe</label>
                <input class="form-input bg-slate-100 font-bold" value="{{ auth()->user()->name }}" readonly>
            </div>
            <div>
                <label class="form-label">Monto recibido</label>
                <input class="form-input" type="number" step="0.01" min="0.01" name="received_amount" value="{{ old('received_amount') }}" data-office-received required>
                @error('received_amount') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Fecha de pago</label>
                <input class="form-input" type="datetime-local" name="paid_at" value="{{ old('paid_at', $paidAt->format('Y-m-d\TH:i')) }}" required>
                @error('paid_at') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">N. de recibo / referencia</label>
                <input class="form-input" name="reference_number" value="{{ old('reference_number') }}" placeholder="Opcional; se generara recibo interno al confirmar">
                @error('reference_number') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div class="xl:col-span-3">
                <label class="form-label">Observacion</label>
                <textarea class="form-input" name="observations" rows="3" data-uppercase>{{ old('observations') }}</textarea>
                @error('observations') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
        </section>

        <div class="flex flex-col gap-3 sm:flex-row">
            <button class="btn-primary">Registrar afiliacion y confirmar pago</button>
            <a class="btn-secondary" href="{{ route('affiliates.index') }}">Cancelar</a>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const plan = document.querySelector('[data-office-plan]');
            const total = document.querySelector('[data-office-plan-total]');
            const received = document.querySelector('[data-office-received]');
            const syncAmount = () => {
                const option = plan?.selectedOptions?.[0];
                const amount = option?.dataset?.amount;
                const currency = option?.dataset?.currency || 'BOB';
                if (!amount) {
                    if (total) total.value = 'Seleccione un plan';
                    return;
                }
                if (total) total.value = `${currency} ${Number(amount).toFixed(2)}`;
                if (received && !received.value) received.value = Number(amount).toFixed(2);
            };
            plan?.addEventListener('change', syncAmount);
            syncAmount();
        });
    </script>
</x-layouts.app>
