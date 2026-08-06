<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('audit.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', Rule::in(array_keys(config('internal_roles.labels', [])))],
            'action' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'string', 'max:60'],
            'entity' => ['nullable', 'string', 'max:160'],
            'ip' => ['nullable', 'string', 'max:45'],
            'request_id' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function filters(): array
    {
        return collect($this->validated())->filter(fn ($value) => filled($value))->all();
    }
}
