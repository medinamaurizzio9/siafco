<?php

namespace App\Http\Requests\Admin\Store;

use App\Models\StoreCategory;
use App\Models\StoreCoupon;
use App\Models\StoreProduct;
use App\Services\StoreCouponCodeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store.manage-coupons') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? app(StoreCouponCodeService::class)->normalize((string) $this->input('code')) : null,
            'target_products' => array_values(array_unique(array_filter((array) $this->input('target_products', [])))),
            'target_categories' => array_values(array_unique(array_filter((array) $this->input('target_categories', [])))),
            'active' => $this->boolean('active'),
        ]);
    }

    public function rules(): array
    {
        $coupon = $this->route('coupon');
        $codeRule = $coupon ? ['nullable', 'string', 'min:3', 'max:80'] : ['required', 'string', 'min:3', 'max:80'];

        return [
            'code' => $codeRule,
            'type' => ['required', Rule::in([StoreCoupon::TYPE_PERCENTAGE, StoreCoupon::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'global_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_affiliate_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'active' => ['nullable', 'boolean'],
            'target_products' => ['array'],
            'target_products.*' => [Rule::exists(StoreProduct::class, 'id')->where('active', true)->whereNull('deleted_at')],
            'target_categories' => ['array'],
            'target_categories.*' => [Rule::exists(StoreCategory::class, 'id')->where('active', true)->whereNull('deleted_at')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') === StoreCoupon::TYPE_PERCENTAGE && (float) $this->input('value') > 100) {
                    $validator->errors()->add('value', 'El porcentaje no puede superar 100.');
                }

                $coupon = $this->route('coupon');
                if ($this->filled('code')) {
                    $hash = app(StoreCouponCodeService::class)->hash((string) $this->input('code'));
                    $duplicate = StoreCoupon::query()
                        ->active()
                        ->where('code_hash', $hash)
                        ->when($coupon, fn ($query) => $query->whereKeyNot($coupon->id))
                        ->exists();

                    if ($duplicate) {
                        $validator->errors()->add('code', 'Ya existe un cupón activo con este código.');
                    }
                }
            },
        ];
    }

    public function couponData(): array
    {
        $data = $this->safe()->except(['code', 'target_products', 'target_categories']);
        $data['minimum_amount'] = $data['minimum_amount'] ?? 0;
        $data['active'] = $this->boolean('active');

        if ($this->filled('code')) {
            $data['code_encrypted'] = (string) $this->input('code');
        }

        return $data;
    }

    public function targetProductIds(): array
    {
        return array_map('intval', (array) $this->input('target_products', []));
    }

    public function targetCategoryIds(): array
    {
        return array_map('intval', (array) $this->input('target_categories', []));
    }
}
