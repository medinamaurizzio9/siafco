<x-layouts.app title="Venta de acciones">
    <form class="section-card grid gap-4 md:grid-cols-2" method="post" enctype="multipart/form-data" action="{{ route('investments.lots.store') }}">
        @csrf
        <div class="md:col-span-2">
            <label class="form-label">Accionista</label>
            <select class="form-input" name="investor_id" required>
                @foreach($investors as $investor)
                    <option value="{{ $investor->id }}" @selected((string) $selectedInvestor === (string) $investor->id)>{{ $investor->investor_number }} - {{ $investor->person->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Fecha compra</label>
            <input class="form-input" type="date" name="purchase_date" value="{{ old('purchase_date', now()->format('Y-m-d')) }}">
        </div>
        <div>
            <label class="form-label">Cantidad de acciones</label>
            <input class="form-input" type="number" name="shares_quantity" min="1" value="{{ old('shares_quantity', 1) }}" required>
        </div>
        <div>
            <label class="form-label">Metodo de pago</label>
            <input class="form-input" name="payment_method" required value="{{ old('payment_method', 'Transferencia') }}">
        </div>
        <div>
            <label class="form-label">Referencia</label>
            <input class="form-input" name="payment_reference" value="{{ old('payment_reference') }}">
        </div>
        <div>
            <label class="form-label">Comprobante</label>
            <input class="form-input" type="file" name="payment_receipt">
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Notas</label>
            <textarea class="form-input" name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>
        <div class="md:col-span-2 flex gap-3">
            <button class="btn-primary">Registrar venta</button>
            <a class="btn-secondary" href="{{ route('investments.lots.index') }}">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
