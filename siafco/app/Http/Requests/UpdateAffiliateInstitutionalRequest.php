<?php

namespace App\Http\Requests;

use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAffiliateInstitutionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('affiliates.update_institutional') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(TextNormalizer::fields($this->only([
            'regional',
            'institution',
            'position',
            'affiliate_type',
            'administrative_notes',
        ]), ['regional', 'institution', 'position', 'affiliate_type', 'administrative_notes']));
    }

    public function rules(): array
    {
        return [
            'regional' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'affiliate_type' => ['nullable', 'string', 'max:80'],
            'administrative_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
