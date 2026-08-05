<?php

namespace App\Http\Requests\Admin\Store;

use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store.manage-settings') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(TextNormalizer::normalizeFields($this->all(), [
            'pickup_instructions',
            'shipping_instructions',
        ]));
    }

    public function rules(): array
    {
        return [
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'remove_whatsapp_number' => ['nullable', 'boolean'],
            'pickup_enabled' => ['nullable', 'boolean'],
            'shipping_enabled' => ['nullable', 'boolean'],
            'pickup_instructions' => ['nullable', 'string', 'max:2000'],
            'shipping_instructions' => ['nullable', 'string', 'max:2000'],
            'default_currency' => ['required', 'in:BOB'],
            'max_receipt_size_kb' => ['required', 'integer', 'min:256', 'max:10240'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return array_merge($data, [
            'whatsapp_enabled' => $this->boolean('whatsapp_enabled'),
            'pickup_enabled' => $this->boolean('pickup_enabled'),
            'shipping_enabled' => $this->boolean('shipping_enabled'),
            'remove_whatsapp_number' => $this->boolean('remove_whatsapp_number'),
        ]);
    }
}
