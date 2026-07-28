@props([
    'status',
    'showDescription' => false,
    'size' => 'md',
])

@php
    use App\Support\AffiliationStatusPresenter;

    $label = AffiliationStatusPresenter::label($status);
    $description = AffiliationStatusPresenter::description($status);
    $badgeClasses = AffiliationStatusPresenter::badgeClasses($status);
    $sizeClasses = match ($size) {
        'sm' => 'px-2.5 py-1 text-xs',
        'lg' => 'px-4 py-2 text-base',
        default => 'px-3 py-1.5 text-sm',
    };
@endphp

<div {{ $attributes->class(['space-y-2']) }}>
    <span class="inline-flex items-center rounded-md font-semibold uppercase {{ $badgeClasses }} {{ $sizeClasses }}">
        {{ $label }}
    </span>
    @if($showDescription)
        <p class="max-w-2xl text-sm leading-6 text-slate-600">{{ $description }}</p>
    @endif
</div>
