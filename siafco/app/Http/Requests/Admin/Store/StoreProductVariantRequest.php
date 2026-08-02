<?php

namespace App\Http\Requests\Admin\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store.manage-products') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => str($this->input('type'))->squish()->upper()->toString(),
            'name' => str($this->input('name'))->squish()->upper()->toString(),
            'sku_suffix' => $this->filled('sku_suffix') ? str($this->input('sku_suffix'))->squish()->upper()->toString() : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'sku_suffix' => ['nullable', 'string', 'max:40'],
            'price_delta' => ['required', 'numeric', 'min:-9999999999.99', 'max:9999999999.99'],
            'active' => ['nullable', 'boolean'],
            'order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->route('product');
                $variant = $this->route('variant');
                $finalPrice = (float) $product->affiliate_price + (float) $this->input('price_delta', 0);

                if ($finalPrice < 0) {
                    $validator->errors()->add('price_delta', 'La diferencia deja el precio final por debajo de cero.');
                }

                $duplicate = $product->variants()
                    ->where('type', $this->input('type'))
                    ->where('name', $this->input('name'))
                    ->when($variant, fn ($q) => $q->whereKeyNot($variant->id))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('name', 'Ya existe una variante equivalente para este producto.');
                }

                if (! $variant && $product->variants()->count() >= 50) {
                    $validator->errors()->add('type', 'El producto alcanzó el máximo de variantes permitido.');
                }
            },
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        $data['active'] = $this->boolean('active');

        if ($key !== null) {
            return data_get($data, $key, $default);
        }

        return $data;
    }
}
