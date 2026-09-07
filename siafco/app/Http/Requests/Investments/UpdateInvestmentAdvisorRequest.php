<?php

namespace App\Http\Requests\Investments;

use Illuminate\Validation\Rule;

class UpdateInvestmentAdvisorRequest extends StoreInvestmentAdvisorRequest
{
    public function rules(): array
    {
        $advisor = $this->route('advisor');

        return [
            ...parent::rules(),
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('investment_advisors', 'user_id')->ignore($advisor),
            ],
        ];
    }
}
