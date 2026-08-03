<?php

namespace App\Http\Controllers\Store;

use App\Exceptions\StoreIdempotencyConflictException;
use App\Http\Controllers\Controller;
use App\Services\Store\StoreCartService;
use App\Services\Store\StoreOrderService;
use App\Support\StoreDeliveryMethod;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function show(Request $request, StoreCartService $cart)
    {
        $cart->normalizedLines();
        if (! $cart->lines()) {
            return redirect()->route('store.cart.show')->with('warning', 'El carrito está vacío.');
        }

        $key = session('store_checkout.idempotency_key') ?: (string) Str::uuid();
        session(['store_checkout.idempotency_key' => $key]);
        $delivery = $this->deliveryFrom($request, true);
        $coupon = $request->input('coupon_code');

        try {
            $quote = $cart->quote($request->user()->affiliate, $delivery, $coupon);
        } catch (ValidationException $exception) {
            $quote = $cart->quote($request->user()->affiliate, ['method' => StoreDeliveryMethod::PICKUP]);
            session()->flash('warning', collect($exception->errors())->flatten()->first());
        }

        return view('store.checkout.show', [
            'quote' => $quote,
            'cart' => $cart,
            'idempotencyKey' => $key,
            'delivery' => $delivery,
            'couponCode' => $coupon,
        ]);
    }

    public function store(Request $request, StoreCartService $cart, StoreOrderService $orders)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'delivery_method' => ['required', 'in:pickup,shipping'],
            'department' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'zone' => ['nullable', 'string', 'max:160'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'coupon_code' => ['nullable', 'string', 'max:80'],
        ]);

        if ($data['idempotency_key'] !== session('store_checkout.idempotency_key')) {
            return back()->with('warning', 'La sesión de checkout venció. Intenta nuevamente.');
        }

        $data = TextNormalizer::normalizeFields($data, [
            'department',
            'city',
            'zone',
            'delivery_address',
        ]);

        try {
            $order = $orders->create($request->user()->affiliate, [
                'items' => $cart->payloadItems(),
                'delivery' => [
                    'method' => $data['delivery_method'],
                    'department' => $data['department'] ?? null,
                    'city' => $data['city'] ?? null,
                    'zone' => $data['zone'] ?? null,
                    'address' => $data['delivery_address'] ?? null,
                ],
                'coupon_code' => $data['coupon_code'] ?? null,
            ], $data['idempotency_key']);
        } catch (StoreIdempotencyConflictException) {
            return back()->with('warning', 'Este checkout ya fue usado con datos diferentes. Revisa el pedido antes de intentar de nuevo.');
        }

        $cart->clear();
        session(['store_checkout.idempotency_key' => (string) Str::uuid()]);

        return redirect()->route('store.orders.show', $order)->with('status', 'Pedido creado correctamente.');
    }

    private function deliveryFrom(Request $request, bool $fallback): array
    {
        return [
            'method' => $request->input('delivery_method', $fallback ? StoreDeliveryMethod::PICKUP : null),
            'department' => $request->input('department'),
            'city' => $request->input('city'),
            'zone' => $request->input('zone'),
            'address' => $request->input('delivery_address'),
        ];
    }
}
