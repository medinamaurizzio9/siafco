<?php

namespace App\Support;

final class InvestmentProspectInteractionType
{
    public static function types(): array { return ['call' => 'LLAMADA', 'whatsapp' => 'WHATSAPP', 'meeting' => 'REUNIÓN', 'note' => 'NOTA', 'follow_up' => 'SEGUIMIENTO', 'other' => 'OTRO']; }
    public static function channels(): array { return ['call' => 'Llamada', 'whatsapp' => 'WhatsApp', 'in_person' => 'Presencial', 'internal' => 'Interno', 'other' => 'Otro']; }
    public static function typeLabel(string $value): string { return self::types()[$value] ?? str($value)->headline(); }
    public static function channelLabel(?string $value): string { return $value ? (self::channels()[$value] ?? str($value)->headline()) : 'Sin canal'; }
}
