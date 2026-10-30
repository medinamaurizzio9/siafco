<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRrhhSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('rrhh.sales.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null]);
    }

    public function rules(): array
    {
        return [
            'sold_on' => ['required', 'date', 'before_or_equal:today'],
            'worker_id' => ['required', 'integer', 'exists:rrhh_workers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:rrhh_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
