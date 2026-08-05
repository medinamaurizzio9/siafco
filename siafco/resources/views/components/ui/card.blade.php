@props([
    'variant' => 'default',
    'title' => null,
    'eyebrow' => null,
])

@php
    $class = match ($variant) {
        'form' => 'card card-form',
        'summary' => 'card card-summary',
        'action' => 'card card-action',
        'kpi' => 'metric-card',
        default => 'section-card',
    };
@endphp

<section {{ $attributes->class([$class]) }}>
    @if($title || $eyebrow)
        <header class="mb-4">
            @if($eyebrow)<p class="ds-title-eyebrow">{{ $eyebrow }}</p>@endif
            @if($title)<h2 class="text-xl font-black text-siafco-primary-900">{{ $title }}</h2>@endif
        </header>
    @endif
    {{ $slot }}
</section>
