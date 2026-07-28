<?php

namespace App\Support;

final class AffiliationStatusPresenter
{
    public static function label(?string $status): string
    {
        return match (self::normalize($status)) {
            'pending_payment', 'pendiente_pago', 'pending', 'pendiente' => 'Pendiente de pago',
            'payment_submitted' => 'Pago enviado para revisión',
            'under_review', 'pago_en_revision' => 'Pago en revisión',
            'approved' => 'Afiliación aprobada',
            'active', 'activo', 'confirmed', 'confirmado' => 'Afiliado activo',
            'rejected', 'rechazado', 'observado' => 'Solicitud observada',
            'cancelled' => 'Solicitud cancelada',
            'suspended', 'suspendido' => 'Afiliación suspendida',
            'inactive', 'inactivo' => 'Afiliación inactiva',
            default => self::fallbackLabel($status),
        };
    }

    public static function description(?string $status): string
    {
        return match (self::normalize($status)) {
            'pending_payment', 'pendiente_pago', 'pending', 'pendiente' =>
                'Tu solicitud fue registrada correctamente. Para continuar con el proceso, realiza el pago y registra el comprobante correspondiente.',
            'payment_submitted' =>
                'Hemos recibido la información de tu pago. Nuestro equipo verificará el comprobante antes de activar tu afiliación.',
            'under_review', 'pago_en_revision' =>
                'Secretaría está verificando tu comprobante y los datos registrados en tu solicitud.',
            'approved' =>
                'Tu solicitud fue aprobada. Estamos completando la activación de tu cuenta y la generación de tu credencial.',
            'active', 'activo', 'confirmed', 'confirmado' =>
                'Tu afiliación está activa. Ya puedes ingresar a tu panel, descargar tu credencial y acceder a los servicios habilitados.',
            'rejected', 'rechazado', 'observado' =>
                'Tu solicitud presenta una observación. Revisa el detalle registrado por Secretaría y realiza la corrección solicitada.',
            'cancelled' =>
                'La solicitud fue cancelada. Comunícate con la institución si necesitas aclaraciones o deseas iniciar un nuevo proceso.',
            'suspended', 'suspendido' =>
                'Tu afiliación se encuentra suspendida. Comunícate con Secretaría para recibir orientación.',
            'inactive', 'inactivo' =>
                'Tu afiliación se encuentra inactiva. Comunícate con Secretaría para conocer los pasos de reactivación.',
            default => 'Consulta el estado actual y las observaciones de tu solicitud.',
        };
    }

    public static function badgeClasses(?string $status): string
    {
        return match (self::normalize($status)) {
            'pending_payment', 'pendiente_pago', 'pending', 'pendiente' => 'bg-yellow-100 text-yellow-900 border border-yellow-200',
            'payment_submitted' => 'bg-orange-100 text-orange-900 border border-orange-200',
            'under_review', 'pago_en_revision' => 'bg-blue-100 text-blue-900 border border-blue-200',
            'approved' => 'bg-emerald-100 text-emerald-900 border border-emerald-200',
            'active', 'activo', 'confirmed', 'confirmado' => 'bg-green-100 text-green-900 border border-green-200',
            'rejected', 'rechazado', 'observado' => 'bg-red-100 text-red-900 border border-red-200',
            'cancelled', 'inactive', 'inactivo' => 'bg-gray-100 text-gray-800 border border-gray-200',
            'suspended', 'suspendido' => 'bg-purple-100 text-purple-900 border border-purple-200',
            default => 'bg-slate-100 text-slate-800 border border-slate-200',
        };
    }

    public static function icon(?string $status): string
    {
        return match (self::normalize($status)) {
            'pending_payment', 'pendiente_pago', 'pending', 'pendiente' => 'clock',
            'payment_submitted' => 'receipt',
            'under_review', 'pago_en_revision' => 'search',
            'approved' => 'check-circle',
            'active', 'activo', 'confirmed', 'confirmado' => 'shield-check',
            'rejected', 'rechazado', 'observado' => 'alert-circle',
            'cancelled' => 'x-circle',
            default => 'information-circle',
        };
    }

    public static function isPending(?string $status): bool
    {
        return in_array(self::normalize($status), [
            'pending_payment', 'pendiente_pago', 'pending', 'pendiente',
            'payment_submitted', 'under_review', 'pago_en_revision',
        ], true);
    }

    public static function isApproved(?string $status): bool
    {
        return in_array(self::normalize($status), ['approved', 'active', 'activo', 'confirmed', 'confirmado'], true);
    }

    public static function isRejected(?string $status): bool
    {
        return in_array(self::normalize($status), ['rejected', 'rechazado', 'observado', 'cancelled'], true);
    }

    public static function isPaymentSubmitted(?string $status): bool
    {
        return self::normalize($status) === 'payment_submitted';
    }

    public static function currentStep(?string $status): int
    {
        return match (self::normalize($status)) {
            'pending_payment', 'pendiente_pago', 'pending', 'pendiente' => 1,
            'payment_submitted', 'under_review', 'pago_en_revision' => 3,
            'approved', 'active', 'activo', 'confirmed', 'confirmado' => 4,
            'rejected', 'rechazado', 'observado' => 3,
            'cancelled' => 1,
            default => 1,
        };
    }

    private static function normalize(?string $status): string
    {
        if ($status === null) {
            return '';
        }

        return strtolower(trim(str_replace([' ', '-'], '_', $status)));
    }

    private static function fallbackLabel(?string $status): string
    {
        if ($status === null || trim($status) === '') {
            return 'Estado no disponible';
        }

        return ucfirst(strtolower(str_replace(['_', '-'], ' ', trim($status))));
    }
}
