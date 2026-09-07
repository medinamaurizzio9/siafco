<x-layouts.app title="{{ $advisor->exists ? 'Editar asesor' : 'Nuevo asesor' }}">
    <form class="section-card grid gap-4 md:grid-cols-2" method="post" enctype="multipart/form-data" action="{{ $advisor->exists ? route('investments.advisors.update', $advisor) : route('investments.advisors.store') }}">
        @csrf
        @if($advisor->exists) @method('put') @endif

        <div>
            <label class="form-label" for="full_name">Nombre completo</label>
            <input class="form-input" id="full_name" name="full_name" value="{{ old('full_name', $advisor->full_name) }}" required maxlength="255">
            @error('full_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="phone">Celular</label>
            <input class="form-input" id="phone" name="phone" value="{{ old('phone', $advisor->phone) }}" required maxlength="40">
            @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="email">Correo</label>
            <input class="form-input" type="email" id="email" name="email" value="{{ old('email', $advisor->email) }}" maxlength="255">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="user_id">Usuario vinculado</label>
            <select class="form-input" id="user_id" name="user_id">
                <option value="">Sin usuario vinculado</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((string) old('user_id', $advisor->user_id) === (string) $user->id)>{{ $user->name }} — {{ $user->email }}</option>
                @endforeach
            </select>
            @error('user_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label class="form-label" for="photo">Fotografía del asesor</label>
            <input class="form-input" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
            <p class="mt-1 text-xs text-slate-500">JPG, PNG o WEBP. Máximo 3 MB.</p>
            @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @if($advisor->exists && $advisor->photoUrl())<label class="mt-3 inline-flex items-center gap-2"><input type="checkbox" name="remove_photo" value="1"> Quitar foto actual</label>@endif
        </div>
        <div class="md:col-span-2">
            <input type="hidden" name="is_active" value="0">
            <label class="inline-flex items-center gap-2 font-bold text-[#0b1f3a]">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $advisor->exists ? $advisor->is_active : true))>
                Asesor activo
            </label>
        </div>
        <div class="md:col-span-2 flex flex-wrap gap-3">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('investments.advisors.index') }}">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
