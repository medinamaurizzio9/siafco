<?php

namespace App\Http\Requests\Admin\Store;

use App\Models\StoreShippingRate;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShippingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store.manage-shipping') === true;
    }

    protected function prepareForValidation(): void
    {
        $scope = $this->input('scope');
        $department = $this->normalize($this->input('department'));
        $city = $this->normalize($this->input('city'));
        $zone = $this->normalize($this->input('zone'));

        $this->merge([
            'scope' => $scope,
            'department' => in_array($scope, [StoreShippingRate::SCOPE_DEPARTMENT, StoreShippingRate::SCOPE_CITY, StoreShippingRate::SCOPE_ZONE], true) ? $department : null,
            'city' => in_array($scope, [StoreShippingRate::SCOPE_CITY, StoreShippingRate::SCOPE_ZONE], true) ? $city : null,
            'zone' => $scope === StoreShippingRate::SCOPE_ZONE ? $zone : null,
            'currency' => 'BOB',
        ]);
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in([
                StoreShippingRate::SCOPE_NATIONAL,
                StoreShippingRate::SCOPE_DEPARTMENT,
                StoreShippingRate::SCOPE_CITY,
                StoreShippingRate::SCOPE_ZONE,
            ])],
            'department' => ['nullable', 'required_if:scope,department,city,zone', 'string', 'max:120'],
            'city' => ['nullable', 'required_if:scope,city,zone', 'string', 'max:120'],
            'zone' => ['nullable', 'required_if:scope,zone', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['required', 'in:BOB'],
            'active' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rate = $this->route('shippingRate');
                if (! $this->boolean('active')) {
                    return;
                }

                $duplicate = StoreShippingRate::query()
                    ->active()
                    ->where('scope', $this->input('scope'))
                    ->where('department', $this->input('department'))
                    ->where('city', $this->input('city'))
                    ->where('zone', $this->input('zone'))
                    ->when($rate, fn ($query) => $query->whereKeyNot($rate->id))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('scope', 'Ya existe una tarifa activa equivalente.');
                }
            },
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        $data['active'] = $this->boolean('active');
        $data['currency'] = 'BOB';

        if ($key !== null) {
            return data_get($data, $key, $default);
        }

        return $data;
    }

    private function normalize(mixed $value): ?string
    {
        $value = TextNormalizer::uppercase((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
