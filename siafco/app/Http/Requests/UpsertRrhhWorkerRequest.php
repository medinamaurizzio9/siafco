<?php

namespace App\Http\Requests;

use App\Models\RrhhWorker;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertRrhhWorkerRequest extends FormRequest
{
    private bool $hadVirtualFixedData = false;

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('rrhh.workers.manage');
    }

    protected function prepareForValidation(): void
    {
        $virtual = $this->input('type') === RrhhWorker::TYPE_VIRTUAL;
        $this->hadVirtualFixedData = $virtual && ($this->filled('schedule_description') || $this->filled('agreed_amount') || ! in_array($this->input('compensation_mode'), [null, '', RrhhWorker::MODE_COMMISSION_ONLY], true));
        $this->merge([
            'full_name' => TextNormalizer::uppercase((string) $this->input('full_name')),
            'ci' => trim((string) $this->input('ci')),
            'phone' => trim((string) $this->input('phone')),
            'email' => $this->filled('email') ? TextNormalizer::lowercaseEmail((string) $this->input('email')) : null,
            'position' => $this->filled('position') ? TextNormalizer::uppercase((string) $this->input('position')) : null,
            'schedule_description' => $virtual ? null : ($this->filled('schedule_description') ? TextNormalizer::uppercase((string) $this->input('schedule_description')) : null),
            'compensation_mode' => $virtual ? RrhhWorker::MODE_COMMISSION_ONLY : $this->input('compensation_mode'),
            'agreed_amount' => $virtual ? null : $this->input('agreed_amount'),
            'commissions_enabled' => $virtual || $this->boolean('commissions_enabled'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $worker = $this->route('worker');

        return [
            'type' => ['required', Rule::in([RrhhWorker::TYPE_SCHEDULED, RrhhWorker::TYPE_VIRTUAL])],
            'full_name' => ['required', 'string', 'max:180'],
            'ci' => ['required', 'string', 'max:30', Rule::unique('rrhh_workers', 'ci')->ignore($worker)],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'position' => ['nullable', 'string', 'max:120'],
            'joined_on' => ['required', 'date', 'before_or_equal:today'],
            'schedule_description' => ['nullable', 'string', 'max:255'],
            'compensation_mode' => ['required', Rule::in([RrhhWorker::MODE_MONTHLY, RrhhWorker::MODE_DAILY, RrhhWorker::MODE_COMMISSION_ONLY])],
            'agreed_amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'commissions_enabled' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('type') === RrhhWorker::TYPE_SCHEDULED) {
                if (! $this->filled('position')) {
                    $validator->errors()->add('position', 'El cargo es obligatorio para personal con horario.');
                }
                if (! $this->filled('schedule_description')) {
                    $validator->errors()->add('schedule_description', 'Describe el horario acordado.');
                }
                if (! in_array($this->input('compensation_mode'), [RrhhWorker::MODE_MONTHLY, RrhhWorker::MODE_DAILY], true)) {
                    $validator->errors()->add('compensation_mode', 'Selecciona sueldo mensual o jornal diario.');
                }
                if (! $this->filled('agreed_amount')) {
                    $validator->errors()->add('agreed_amount', 'El importe acordado es obligatorio.');
                }
            }
            if ($this->input('type') === RrhhWorker::TYPE_VIRTUAL && $this->hadVirtualFixedData) {
                $validator->errors()->add('type', 'El trabajador virtual solo recibe comisiones y no debe tener horario ni importe fijo.');
            }
        }];
    }
}
