<x-layouts.app title="Nueva reserva">
    <form class="section-card grid gap-4 md:grid-cols-2" method="post" action="{{ route('investments.reservations.store') }}">
        @csrf
        <div class="md:col-span-2">
            <label class="form-label">Accionista</label>
            <select class="form-input" name="investor_id" required>
                @foreach($investors as $investor)
                    <option value="{{ $investor->id }}" @selected((string) $selectedInvestor === (string) $investor->id)>{{ $investor->investor_number }} - {{ $investor->person->full_name }} - CI {{ $investor->person->ci }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Cantidad de acciones</label>
            <input class="form-input" type="number" name="shares_quantity" min="1" value="{{ old('shares_quantity', 1) }}" required>
        </div>
        <div>
            <label class="form-label">Fecha reserva</label>
            <input class="form-input" type="date" name="reservation_date" value="{{ old('reservation_date', now()->format('Y-m-d')) }}">
        </div>
        <div>
            <label class="form-label">Monto recibido</label>
            <input class="form-input" type="number" step="0.01" name="amount_paid" value="{{ old('amount_paid', 0) }}">
        </div>
        <div>
            <label class="form-label">Metodo de pago</label>
            <input class="form-input" name="payment_method" value="{{ old('payment_method') }}">
        </div>
        <div>
            <label class="form-label">Referencia</label>
            <input class="form-input" name="payment_reference" value="{{ old('payment_reference') }}">
        </div>
        <div>
            <label class="form-label">Estado</label>
            <select class="form-input" name="status">
                <option value="active">active</option>
                <option value="pending">pending</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Notas</label>
            <textarea class="form-input" name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>
        <div class="md:col-span-2 flex gap-3">
            <button class="btn-primary">Guardar reserva</button>
            <a class="btn-secondary" href="{{ route('investments.reservations.index') }}">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
