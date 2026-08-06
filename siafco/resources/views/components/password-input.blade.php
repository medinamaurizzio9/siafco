@props([
    'name',
    'label',
    'required' => true,
    'autocomplete' => 'current-password',
    'value' => null,
])

<label for="{{ $name }}" data-field-wrapper>
    @if($label)
        <span class="form-label" data-field-label>{{ $label }} @if($required)<span class="text-red-600" aria-hidden="true">*</span>@endif</span>
    @endif
    <span class="relative block">
        <input id="{{ $name }}" class="form-input pr-12 @error($name) border-red-500 bg-red-50 text-red-900 @enderror"
            type="password" name="{{ $name }}" value="{{ $value }}"
            autocomplete="{{ $autocomplete }}" data-password-input data-validate-field
            data-touched="{{ $errors->has($name) ? 'true' : 'false' }}"
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" aria-describedby="{{ $name }}-error"
            @if($required) required aria-required="true" @endif {{ $attributes }}>
        <button
            class="password-toggle-button"
            type="button" data-password-toggle aria-label="Mostrar {{ mb_strtolower($label) }}" aria-pressed="false" tabindex="0">
            <x-ui.icon name="eye" class="h-5 w-5" data-eye-open />
            <x-ui.icon name="eye-slash" class="hidden h-5 w-5" data-eye-closed />
        </button>
    </span>
    <x-forms.field-error :name="$name" />
</label>
