<?php

namespace App\Http\Requests\Admin\Store;

use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store.manage-products') === true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));
        $modes = array_values(array_filter((array) $this->input('delivery_modes', [])));

        $this->merge([
            'slug' => Str::slug($slug !== '' ? $slug : $name),
            'sku' => Str::upper(trim((string) $this->input('sku'))),
            'delivery_modes' => $modes,
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'store_category_id' => ['required', Rule::exists(StoreCategory::class, 'id')->whereNull('deleted_at')],
            'sku' => ['required', 'string', 'max:80', Rule::unique(StoreProduct::class, 'sku')->ignore($product)],
            'slug' => ['required', 'string', 'max:180', Rule::unique(StoreProduct::class, 'slug')->ignore($product)],
            'name' => ['required', 'string', 'max:180'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'regular_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'affiliate_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'promo_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'promo_starts_at' => ['nullable', 'date'],
            'promo_ends_at' => ['nullable', 'date', 'after_or_equal:promo_starts_at'],
            'availability_status' => ['required', Rule::in(StoreAvailabilityStatus::ALL)],
            'delivery_modes' => ['required', 'array', 'min:1'],
            'delivery_modes.*' => [Rule::in(StoreDeliveryMethod::ALL)],
            'max_quantity_per_order' => ['required', 'integer', 'min:1', 'max:100'],
            'featured' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('promo_price') && (! $this->filled('promo_starts_at') || ! $this->filled('promo_ends_at'))) {
                    $validator->errors()->add('promo_price', 'La promoción requiere fecha de inicio y fin.');
                }
            },
        ];
    }

    public function validated($key = null, $default = null): array
    {
        return parent::validated() + [
            'featured' => $this->boolean('featured'),
            'active' => $this->boolean('active'),
        ];
    }
}
