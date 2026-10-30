<?php

namespace App\Http\Requests;

use App\Models\RrhhProduct;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertRrhhProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('rrhh.products.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => TextNormalizer::uppercase((string) $this->input('code')),
            'name' => TextNormalizer::uppercase((string) $this->input('name')),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('rrhh_products', 'code')->ignore($product)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reference_price' => ['required', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'commission_type' => ['required', Rule::in([RrhhProduct::COMMISSION_FIXED, RrhhProduct::COMMISSION_PERCENTAGE])],
            'commission_value' => ['required', 'decimal:0,4', 'min:0', Rule::when($this->input('commission_type') === RrhhProduct::COMMISSION_PERCENTAGE, ['max:100'])],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
