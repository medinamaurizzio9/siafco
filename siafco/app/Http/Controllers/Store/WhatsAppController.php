<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use App\Models\StoreSetting;
use App\Services\AuditService;
use App\Services\StoreWhatsAppNumberService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WhatsAppController extends Controller
{
    public function store(Request $request, StoreOrder $order, StoreWhatsAppNumberService $numbers)
    {
        abort_unless((int) $order->affiliate_id === (int) $request->user()->affiliate->id, 404);
        $settings = StoreSetting::current();
        if (! $settings->whatsapp_enabled || ! $settings->whatsapp_number_encrypted) {
            throw ValidationException::withMessages(['whatsapp' => 'WhatsApp no está habilitado para la tienda.']);
        }

        $message = $this->message($order->load('items'));
        $url = $numbers->waMeUrl($settings->whatsapp_number_encrypted, $message);
        abort_unless(str_starts_with($url, 'https://wa.me/'), 500);

        $order->forceFill(['whatsapp_opened_at' => now()])->save();
        AuditService::record('mini_tienda.whatsapp_pedido_abierto', $order, [
            'code' => $order->code,
            'whatsapp_hint' => $settings->whatsapp_number_hint,
            'total' => $order->total,
        ]);

        return redirect()->away($url);
    }

    private function message(StoreOrder $order): string
    {
        $lines = $order->items->map(function ($item): string {
            return '- '.$item->quantity.' x '.$item->name_snapshot.($item->variant_snapshot ? ' '.$item->variant_snapshot : '');
        })->implode("\n");

        return "Hola, deseo coordinar mi pedido de la Mini Tienda SIAFCO.\n\n"
            ."Pedido: {$order->code}\n"
            ."Productos:\n{$lines}\n\n"
            .'Entrega: '.($order->delivery_method === 'pickup' ? 'Recojo en oficina' : 'Envio nacional')."\n"
            ."Total: Bs {$order->total}\n"
            ."Estado: {$order->status}\n\n"
            .'Ya registre el pedido en el sistema.';
    }
}
