<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Support\SiafcoDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AffiliationReportService
{
    public const ORIGIN_WEB = 'web';
    public const ORIGIN_ADMINISTRATIVE = 'administrative';
    public const ORIGIN_UNKNOWN = 'unknown';

    public function filters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'code' => ['nullable', 'string', 'max:80'],
            'name' => ['nullable', 'string', 'max:120'],
            'ci' => ['nullable', 'string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:160'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'origin' => ['nullable', Rule::in([self::ORIGIN_WEB, self::ORIGIN_ADMINISTRATIVE, self::ORIGIN_UNKNOWN])],
            'registered_by' => ['nullable', 'integer', 'exists:users,id'],
            'managed_by' => ['nullable', 'integer', 'exists:users,id'],
            'approved_by' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:60'],
        ]);
    }

    public function query(array $filters = []): Builder
    {
        return Affiliate::query()
            ->with([
                'sector:id,name',
                'registrar:id,name,role',
                'manager:id,name,role',
                'approver:id,name,role',
                'publicRequest',
                'publicRequest.reviewer:id,name,role',
                'initialPayment',
                'initialPayment.registrar:id,name,role',
            ])
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $this->whereAffiliationDate($query, $date, 'from'))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $this->whereAffiliationDate($query, $date, 'to'))
            ->when($filters['code'] ?? null, fn (Builder $query, string $value) => $query->where('registration_number', 'like', '%'.$value.'%'))
            ->when($filters['name'] ?? null, fn (Builder $query, string $value) => $query->where('full_name', 'like', '%'.$value.'%'))
            ->when($filters['ci'] ?? null, fn (Builder $query, string $value) => $query->where('ci', 'like', '%'.$value.'%'))
            ->when($filters['organization'] ?? null, function (Builder $query, string $value): void {
                $query->where(function (Builder $inner) use ($value): void {
                    $inner->where('institution', 'like', '%'.$value.'%')
                        ->orWhereHas('sector', fn (Builder $sector) => $sector->where('name', 'like', '%'.$value.'%'));
                });
            })
            ->when($filters['sector_id'] ?? null, fn (Builder $query, string|int $id) => $query->where('sector_id', $id))
            ->when($filters['origin'] ?? null, function (Builder $query, string $origin): void {
                match ($origin) {
                    self::ORIGIN_WEB => $query->where(function (Builder $inner): void {
                        $inner->where('origin', self::ORIGIN_WEB)->orWhereHas('publicRequest');
                    }),
                    self::ORIGIN_ADMINISTRATIVE => $query->where(function (Builder $inner): void {
                        $inner->where('origin', self::ORIGIN_ADMINISTRATIVE)
                            ->orWhere(function (Builder $legacy): void {
                                $legacy->whereNull('origin')->whereDoesntHave('publicRequest');
                            });
                    }),
                    self::ORIGIN_UNKNOWN => $query->whereNull('origin')->whereDoesntHave('publicRequest')->whereDoesntHave('initialPayment'),
                    default => null,
                };
            })
            ->when($filters['registered_by'] ?? null, function (Builder $query, string|int $id): void {
                $query->where(function (Builder $inner) use ($id): void {
                    $inner->where('registered_by', $id)
                        ->orWhere(function (Builder $legacy) use ($id): void {
                            $legacy->whereNull('registered_by')
                                ->whereDoesntHave('publicRequest')
                                ->whereHas('initialPayment', fn (Builder $payment) => $payment->where('registered_by', $id));
                        });
                });
            })
            ->when($filters['managed_by'] ?? null, fn (Builder $query, string|int $id) => $query->where('managed_by', $id))
            ->when($filters['approved_by'] ?? null, function (Builder $query, string|int $id): void {
                $query->where(function (Builder $inner) use ($id): void {
                    $inner->where('approved_by', $id)
                        ->orWhere(function (Builder $legacy) use ($id): void {
                            $legacy->whereNull('approved_by')
                                ->whereHas('publicRequest', fn (Builder $request) => $request->where('reviewed_by', $id));
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function row(Affiliate $affiliate): array
    {
        $isWeb = $this->origin($affiliate) === self::ORIGIN_WEB;
        $registeredBy = $isWeb
            ? 'AUTOAFILIACIÓN WEB'
            : ($affiliate->registrar?->name
                ?? $affiliate->initialPayment?->registrar?->name
                ?? 'Sin registro');

        return [
            'affiliation_date' => $affiliate->publicRequest?->submitted_at ?? $affiliate->created_at,
            'code' => $affiliate->registration_number ?: 'Pendiente',
            'name' => $affiliate->full_name,
            'ci' => $affiliate->ci,
            'organization' => $affiliate->institution ?: 'No disponible',
            'sector' => $affiliate->sector?->name ?: 'No disponible',
            'origin' => $this->originLabel($this->origin($affiliate)),
            'registered_by' => $registeredBy,
            'managed_by' => $affiliate->manager?->name ?? 'No disponible',
            'approved_by' => $affiliate->approver?->name ?? $affiliate->publicRequest?->reviewer?->name ?? 'No disponible',
            'approved_at' => $affiliate->approved_at ?? $affiliate->publicRequest?->reviewed_at,
            'status' => $affiliate->status,
        ];
    }

    public function origin(Affiliate $affiliate): string
    {
        if ($affiliate->origin) {
            return $affiliate->origin;
        }

        if ($affiliate->publicRequest) {
            return self::ORIGIN_WEB;
        }

        if ($affiliate->initialPayment?->registered_by) {
            return self::ORIGIN_ADMINISTRATIVE;
        }

        return self::ORIGIN_UNKNOWN;
    }

    public function originLabel(string $origin): string
    {
        return match ($origin) {
            self::ORIGIN_WEB => 'WEB / AUTOAFILIACIÓN',
            self::ORIGIN_ADMINISTRATIVE => 'REGISTRO ADMINISTRATIVO',
            default => 'OTRO / NO DISPONIBLE',
        };
    }

    public function originOptions(): array
    {
        return [
            self::ORIGIN_WEB => $this->originLabel(self::ORIGIN_WEB),
            self::ORIGIN_ADMINISTRATIVE => $this->originLabel(self::ORIGIN_ADMINISTRATIVE),
            self::ORIGIN_UNKNOWN => $this->originLabel(self::ORIGIN_UNKNOWN),
        ];
    }

    private function whereAffiliationDate(Builder $query, string $date, string $bound): void
    {
        [$from, $to] = SiafcoDate::utcDayBounds($date);
        $operator = $bound === 'from' ? '>=' : '<=';
        $value = $bound === 'from' ? $from : $to;

        $query->where(function (Builder $inner) use ($operator, $value): void {
            $inner->whereHas('publicRequest', fn (Builder $request) => $request->where('submitted_at', $operator, $value))
                ->orWhere(function (Builder $administrative) use ($operator, $value): void {
                    $administrative->whereDoesntHave('publicRequest')->where('created_at', $operator, $value);
                });
        });
    }
}
