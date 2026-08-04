<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreProduct;
use App\Models\StoreProductVariant;
use App\Services\Store\StoreCartService;
use App\Support\StoreDeliveryMethod;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(StoreCartService $cart)
    {
        $cart->normalizedLines();
        $quote = null;
        if ($cart->lines()) {
            $quote = $cart->quote(request()->user()->affiliate, ['method' => StoreDeliveryMethod::PICKUP]);
        }

        return view('store.cart.show', ['cart' => $cart, 'quote' => $quote]);
    }

    public function store(Request $request, StoreCartService $cart)
    {
        $data = $request->validate([
            'product_public_code' => ['required', 'uuid'],
            'variant_public_code' => ['nullable', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $product = StoreProduct::query()->where('public_code', $data['product_public_code'])->firstOrFail();
        $variant = ! empty($data['variant_public_code'])
            ? StoreProductVariant::query()->where('public_code', $data['variant_public_code'])->firstOrFail()
            : null;
        $cart->add($product, $variant, (int) $data['quantity']);

        return redirect()->route('store.cart.show')->with('status', 'Producto agregado al carrito.');
    }

    public function update(Request $request, StoreCartService $cart, string $lineKey)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        $cart->update($lineKey, (int) $data['quantity']);

        return back()->with('status', 'Carrito actualizado.');
    }

    public function destroy(StoreCartService $cart, string $lineKey)
    {
        $cart->remove($lineKey);

        return back()->with('status', 'Producto retirado.');
    }

    public function clear(StoreCartService $cart)
    {
        $cart->clear();

        return back()->with('status', 'Carrito vacío.');
    }
}
