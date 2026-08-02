<?php

namespace App\Http\Requests\Api\Mobile\V1;

use App\Support\PublicAffiliationValidation;

class StoreAffiliationRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PublicAffiliationValidation::registrationRules('terms_accepted', 'privacy_accepted') + [
            'device_name' => ['nullable', 'string', 'max:120'],
            'status' => ['prohibited'],
            'registration_number' => ['prohibited'],
            'verification_token' => ['prohibited'],
            'public_token' => ['prohibited'],
            'user_id' => ['prohibited'],
            'affiliate_id' => ['prohibited'],
            'person_id' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'amount_due' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return PublicAffiliationValidation::registrationMessages('terms_accepted', 'privacy_accepted') + [
            'status.prohibited' => 'No puedes enviar campos administrativos.',
            'registration_number.prohibited' => 'No puedes enviar campos administrativos.',
            'verification_token.prohibited' => 'No puedes enviar campos administrativos.',
            'public_token.prohibited' => 'No puedes enviar campos administrativos.',
            'user_id.prohibited' => 'No puedes enviar campos administrativos.',
            'affiliate_id.prohibited' => 'No puedes enviar campos administrativos.',
            'person_id.prohibited' => 'No puedes enviar campos administrativos.',
            'reviewed_by.prohibited' => 'No puedes enviar campos administrativos.',
            'amount_due.prohibited' => 'No puedes enviar campos administrativos.',
        ];
    }
}
