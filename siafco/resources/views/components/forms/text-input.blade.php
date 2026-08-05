@props([
    'name', 'label', 'type' => 'text', 'required' => false, 'optional' => false,
    'value' => null, 'help' => null, 'uppercase' => false,
])

<label for="{{ $name }}" class="block" data-field-wrapper>
    <span class="form-label" data-field-label>
        {{ $label }}
        @if($required)<span class="text-red-600" aria-hidden="true">*</span>@endif
        @if($optional)<span class="font-normal text-slate-500">(opcional)</span>@endif
    </span>
    <input
        id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ old($name, $value) }}"
        class="form-input @error($name) border-red-500 bg-red-50 text-red-900 @enderror"
        @if($required) required aria-required="true" @endif
        @if($uppercase) data-uppercase @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        aria-describedby="{{ $name }}-error{{ $help ? ' '.$name.'-help' : '' }}"
        data-validate-field data-touched="{{ $errors->has($name) ? 'true' : 'false' }}"
        {{ $attributes }}
    >
    @if($help)<p id="{{ $name }}-help" class="form-helper">{{ $help }}</p>@endif
    <x-forms.field-error :name="$name" />
</label>
