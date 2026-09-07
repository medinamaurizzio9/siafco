<?php

namespace App\Http\Requests\Investments;

use Illuminate\Validation\Rule;

class StoreInvestmentProspectAdminRequest extends StoreInvestmentProspectPublicRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'advisor_id' => ['required', 'integer', Rule::exists('investment_advisors', 'id')->where('is_active', true)],
            'service_rating' => ['prohibited'],
        ];
    }
}
