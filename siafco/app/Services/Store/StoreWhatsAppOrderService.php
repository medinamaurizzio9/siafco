<?php

namespace App\Services\Store;

use App\Models\StoreOrder;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\AuditService;
use App\Services\StoreWhatsAppNumberService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreWhatsAppOrderService
{
    public function __construct(private readonly StoreWhatsAppNumberService $numbers) {}

    public function open(StoreOrder $order, User $user): string
    {
        $settings = StoreSetting::current();
        if (! $settings->whatsapp_enabled || ! $settings->whatsapp_number_encrypted) {
            throw ValidationException::withMessages(['whatsapp' => 'WhatsApp no esta habilitado para la tienda.']);
        }

        $url = $this->numbers->waMeUrl($settings->whatsapp_number_encrypted, $this->message($order->loadMissing('items'), $user));
        abort_unless(str_starts_with($url, 'https://wa.me/'), 500);

        $order->forceFill(['whatsapp_opened_at' => now()])->save();
        AuditService::record('mini_tienda.whatsapp_pedido_abierto', $order, [
            'code' => $order->code,
            'whatsapp_hint' => $settings->whatsapp_number_hint,
            'total' => $order->total,
            'actor_user_id' => $user->id,
        ]);

        return $url;
    }

    public function messagePreview(StoreOrder $order): string
    {
        return "Pedido {$order->code} - Total Bs {$order->total}";
    }

    private function message(StoreOrder $order, User $user): string
    {
        $affiliate = $user->affiliate()->with('person')->first();
        $affiliateName = $affiliate?->full_name
            ?: $affiliate?->person?->full_name
            ?: $user->name;
        $registrationNumber = $affiliate?->registration_number ?: 'NO ASIGNADO';
        $lines = $order->items->map(function ($item): string {
            return '- '.$item->quantity.' x '.$item->name_snapshot.($item->variant_snapshot ? ' '.$item->variant_snapshot : '');
        })->implode("\n");

        return "Hola, soy {$affiliateName}.\n\n"
            ."Codigo de afiliado: {$registrationNumber}\n\n"
            ."Deseo coordinar mi pedido de la Mini Tienda SIAFCO.\n\n"
            ."Pedido: {$order->code}\n"
            ."Productos:\n{$lines}\n\n"
            .'Entrega: '.($order->delivery_method === 'pickup' ? 'RECOJO EN OFICINA' : 'ENVIO NACIONAL')."\n"
            ."Total: Bs {$order->total}\n"
            .'Estado: '.Str::of($order->status)->replace('_', ' ')->headline()->toString()."\n\n"
            .'El pedido ya fue registrado en el sistema.';
    }
}
