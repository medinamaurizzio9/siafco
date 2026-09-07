<?php

namespace App\Http\Requests\Investments;

class StoreInvestmentProspectManualRequest extends StoreInvestmentProspectPublicRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'service_rating' => ['prohibited'], 'advisor_id' => ['prohibited']];
    }
}
