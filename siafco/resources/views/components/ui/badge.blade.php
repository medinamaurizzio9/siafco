@props(['variant' => 'default'])

@php
    $class = match ($variant) {
        'success', 'active', 'activo', 'confirmado' => 'badge badge-success',
        'warning', 'pending', 'pendiente', 'observado' => 'badge badge-warning',
        'danger', 'rejected', 'rechazado', 'suspendido', 'inactivo' => 'badge badge-danger',
        'info', 'gerente', 'cajero' => 'badge badge-info',
        'muted', 'consulta' => 'badge badge-muted',
        default => 'badge',
    };
@endphp

<span {{ $attributes->class([$class]) }}>{{ $slot }}</span>
