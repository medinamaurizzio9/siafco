<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\ActivePlanForSector;

class ChangeAffiliateSectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('affiliates.change_sector') ?? false;
    }

    public function rules(): array
    {
        return [
            'sector_id' => ['required', Rule::exists('sectors', 'id')->where('is_active', true)],
            'affiliation_plan_id' => ['required', new ActivePlanForSector($this->input('sector_id'))],
        ];
    }
}
