<?php

namespace App\Http\Requests\Api\Mobile\V1;

use App\Support\StoreAvailabilityStatus;
use Illuminate\Validation\Rule;

class StoreCatalogRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? str($this->input('search'))->squish()->toString() : null,
            'category' => $this->filled('category') ? str($this->input('category'))->slug()->toString() : null,
            'featured' => $this->has('featured') ? filter_var($this->input('featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            'per_page' => min(30, max(1, (int) $this->input('per_page', 15))),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:120'],
            'featured' => ['nullable', 'boolean'],
            'availability' => ['nullable', 'string', Rule::in([
                StoreAvailabilityStatus::AVAILABLE,
                StoreAvailabilityStatus::SOLD_OUT,
                StoreAvailabilityStatus::COMING_SOON,
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
