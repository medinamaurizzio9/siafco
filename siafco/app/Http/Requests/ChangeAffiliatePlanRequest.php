<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\ActivePlanForSector;

class ChangeAffiliatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('affiliates.change_plan') ?? false;
    }

    public function rules(): array
    {
        return [
            'affiliation_plan_id' => ['required', new ActivePlanForSector($this->route('affiliate')?->sector_id)],
        ];
    }
}
