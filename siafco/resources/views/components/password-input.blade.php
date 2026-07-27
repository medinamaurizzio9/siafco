@props([
    'name',
    'label',
    'required' => true,
    'autocomplete' => 'current-password',
    'value' => null,
])

<label>
    <span class="form-label">{{ $label }}</span>
    <span class="relative block">
        <input class="form-input pr-12" type="password" name="{{ $name }}" value="{{ $value }}"
            autocomplete="{{ $autocomplete }}" data-password-input @required($required) {{ $attributes }}>
        <button
            class="absolute inset-y-0 right-0 grid w-12 place-items-center text-slate-500 hover:text-[#0b1f3a] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#d4af37]"
            type="button" data-password-toggle aria-label="Mostrar {{ mb_strtolower($label) }}" aria-pressed="false">
            <svg class="h-5 w-5" data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            <svg class="hidden h-5 w-5" data-eye-closed viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="m3 3 18 18M10.6 10.6A2 2 0 0 0 13.4 13.4M9.9 4.2A10.7 10.7 0 0 1 12 4c6.5 0 10 8 10 8a18 18 0 0 1-2 3.1M6.6 6.6C3.6 8.6 2 12 2 12s3.5 8 10 8a9.8 9.8 0 0 0 4.1-.9"/>
            </svg>
        </button>
    </span>
</label>
