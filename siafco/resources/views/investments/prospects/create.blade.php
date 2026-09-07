<x-layouts.app title="Nuevo prospecto">
    <form class="section-card mx-auto grid max-w-2xl gap-4" method="post" action="{{ route('investments.prospects.store') }}">
        @csrf
        <div><h2 class="text-xl font-black text-[#0b1f3a]">Nuevo prospecto</h2><p class="mt-1 text-sm text-slate-500">Registro manual administrativo en el CRM de inversiones.</p></div>
        <div><label class="form-label" for="full_name">Nombre completo</label><input class="form-input" id="full_name" name="full_name" value="{{ old('full_name') }}" required>@error('full_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="form-label" for="phone">Número de celular</label><input class="form-input" id="phone" name="phone" value="{{ old('phone') }}" inputmode="tel" required>@error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="form-label" for="requested_shares">Cantidad de acciones de interés</label><input class="form-input" type="number" min="1" id="requested_shares" name="requested_shares" value="{{ old('requested_shares') }}" required></div>
        <div><label class="form-label" for="preferred_contact_method">Contacto preferido</label><select class="form-input" id="preferred_contact_method" name="preferred_contact_method"><option value="whatsapp">WhatsApp</option><option value="call" @selected(old('preferred_contact_method') === 'call')>Llamada</option></select></div>
        <div><label class="form-label" for="advisor_id">Asesor responsable</label><select class="form-input" id="advisor_id" name="advisor_id" required><option value="">Seleccione un asesor</option>@foreach($advisors as $advisor)<option value="{{ $advisor->id }}" @selected((string) old('advisor_id') === (string) $advisor->id)>{{ $advisor->advisor_number }} - {{ $advisor->full_name }}</option>@endforeach</select>@error('advisor_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div class="flex flex-wrap gap-3"><button class="btn-primary">Guardar prospecto</button><a class="btn-secondary" href="{{ route('investments.prospects.index') }}">Cancelar</a></div>
    </form>
</x-layouts.app>
