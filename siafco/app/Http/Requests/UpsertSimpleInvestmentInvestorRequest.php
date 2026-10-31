<?php

namespace App\Http\Requests;

use App\Services\SimpleInvestmentInvestorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSimpleInvestmentInvestorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('simple_investments.investors.manage');
    }

    protected function prepareForValidation(): void
    {
        $data = ['full_name' => mb_strtoupper(trim((string) $this->input('full_name'))), 'ci' => trim((string) $this->input('ci')), 'is_active' => $this->boolean('is_active')];
        foreach (['ci_complement', 'expedition_department', 'phone', 'email', 'address', 'notes'] as $field) {
            $data[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
        }
        $data['identity_key'] = app(SimpleInvestmentInvestorService::class)->identityKey([...$this->all(), ...$data]);
        $this->merge($data);
    }

    public function rules(): array
    {
        $investor = $this->route('investor');
        $identity = app(SimpleInvestmentInvestorService::class)->identityKey($this->all());

        return [
            'full_name' => ['required', 'string', 'max:180'], 'ci' => ['required', 'string', 'max:30'],
            'ci_complement' => ['nullable', 'string', 'max:10'], 'expedition_department' => ['nullable', 'string', 'max:30'],
            'identity_key' => [Rule::unique('simple_investment_investors')->ignore($investor), Rule::in([$identity])],
            'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'], 'photo' => ['nullable', 'image', 'max:4096'],
            'registered_on' => ['required', 'date', 'before_or_equal:today'], 'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
