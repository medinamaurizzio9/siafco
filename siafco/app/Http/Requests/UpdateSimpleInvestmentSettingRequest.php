<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSimpleInvestmentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('simple_investments.settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return ['product_name' => ['required', 'string', 'max:80'], 'unit_price' => ['required', 'decimal:0,2', 'min:0.01'], 'minimum_units' => ['required', 'integer', 'min:1', 'max:20'], 'maximum_units' => ['required', 'integer', 'min:1', 'max:20', 'gte:minimum_units'], 'first_schedule_days' => ['required', 'integer', 'min:1', 'max:365'], 'schedule_interval_days' => ['required', 'integer', 'min:1', 'max:365'], 'is_active' => ['required', 'boolean']];
    }
}
