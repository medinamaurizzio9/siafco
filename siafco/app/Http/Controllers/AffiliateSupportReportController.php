<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliateSupportAttributionService;
use App\Support\AffiliateSupportChannel;
use App\Support\AffiliationStatusPresenter;
use App\Support\PaymentStatus;
use App\Support\SiafcoDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AffiliateSupportReportController extends Controller
{
    public function __construct(private readonly AffiliateSupportAttributionService $attribution) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPermission('affiliate_support_reports.view'), 403);

        $filters = $this->filters($request);
        $query = $this->query($filters);
        $summaryRows = (clone $query)->get();
        $detailRows = (clone $query)->paginate(25)->withQueryString();

        return view('reports.affiliate-support', [
            'filters' => $filters,
            'affiliates' => $detailRows,
            'summaryCards' => $this->summaryCards($summaryRows),
            'responsibleRows' => $this->responsibleRows($summaryRows),
            'responsibles' => $this->responsibleOptions(),
            'roles' => config('internal_roles.labels', []),
            'channels' => [
                AffiliateSupportChannel::WEB => AffiliateSupportChannel::label(AffiliateSupportChannel::WEB),
                AffiliateSupportChannel::EXPRESS => AffiliateSupportChannel::label(AffiliateSupportChannel::EXPRESS),
                AffiliateSupportChannel::OFFICE => AffiliateSupportChannel::label(AffiliateSupportChannel::OFFICE),
                AffiliateSupportChannel::INTERNAL => AffiliateSupportChannel::label(AffiliateSupportChannel::INTERNAL),
                AffiliateSupportChannel::UNKNOWN => AffiliateSupportChannel::label(AffiliateSupportChannel::UNKNOWN),
            ],
            'sectors' => Sector::query()->orderBy('name')->get(['id', 'name']),
            'plans' => AffiliationPlan::query()->orderBy('name')->get(['id', 'name']),
            'affiliationStatuses' => $this->affiliationStatuses(),
            'paymentStatuses' => PaymentStatus::allValues(),
            'attribution' => $this->attribution,
            'canExport' => $request->user()?->hasPermission('affiliate_support_reports.export'),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('affiliate_support_reports.export'), 403);

        $filters = $this->filters($request);
        $filename = 'soporte-captacion-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Registro',
                'Nombre',
                'CI',
                'Sector',
                'Plan',
                'Canal',
                'Responsable soporte',
                'Rol responsable',
                'Fecha',
                'Estado afiliacion',
                'Estado pago',
            ]);

            $this->query($filters)->chunk(200, function ($affiliates) use ($handle): void {
                foreach ($affiliates as $affiliate) {
                    $resolved = $this->attribution->resolve($affiliate);
                    $responsible = $resolved['responsible_user'];

                    fputcsv($handle, [
                        $affiliate->registration_number ?: 'Pendiente',
                        $affiliate->full_name,
                        $affiliate->ci,
                        $affiliate->sector?->name,
                        $affiliate->plan?->name,
                        AffiliateSupportChannel::label($resolved['channel']),
                        $responsible?->name ?? ($resolved['attribution_type'] === 'web_assigned' ? 'Sin responsable configurado' : 'Sin responsable'),
                        $responsible?->roleLabel() ?? '',
                        $this->captureDate($affiliate),
                        AffiliationStatusPresenter::label($affiliate->status),
                        PaymentStatus::label($affiliate->latestPayment?->status),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(array $filters): Builder
    {
        return Affiliate::query()
            ->with([
                'sector:id,name',
                'plan:id,name',
                'publicRequest.payment',
                'initialPayment.registrar',
                'latestPayment',
            ])
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $this->whereCaptureDate($query, $date, 'from'))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $this->whereCaptureDate($query, $date, 'to'))
            ->when($filters['responsible_user_id'] ?? null, fn (Builder $query, string $id) => $this->whereResponsible($query, (int) $id))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $this->whereRole($query, $role))
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel) => $this->whereChannel($query, $channel))
            ->when($filters['sector_id'] ?? null, fn (Builder $query, string $id) => $query->where('sector_id', $id))
            ->when($filters['affiliation_plan_id'] ?? null, fn (Builder $query, string $id) => $query->where('affiliation_plan_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->whereHas('payments', fn (Builder $payment) => $payment->where('status', $status)))
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%");
                });
            })
            ->latest('id');
    }

    private function whereCaptureDate(Builder $query, string $date, string $bound): void
    {
        [$from, $to] = SiafcoDate::utcDayBounds($date);
        $operator = $bound === 'from' ? '>=' : '<=';
        $value = $bound === 'from' ? $from : $to;

        $query->where(function (Builder $inner) use ($operator, $value): void {
            $inner->whereHas('publicRequest', fn (Builder $request) => $request->where('submitted_at', $operator, $value))
                ->orWhere(function (Builder $office) use ($operator, $value): void {
                    $office->whereDoesntHave('publicRequest')
                        ->where('created_at', $operator, $value);
                });
        });
    }

    private function whereResponsible(Builder $query, int $userId): void
    {
        $webSupportId = $this->attribution->webSupportUser()?->id;

        $query->where(function (Builder $inner) use ($userId, $webSupportId): void {
            if ($webSupportId === $userId) {
                $inner->orWhereHas('publicRequest');
            }

            $inner->orWhere(function (Builder $office) use ($userId): void {
                $office->whereDoesntHave('publicRequest')
                    ->whereHas('initialPayment', fn (Builder $payment) => $payment
                        ->whereIn('source', ['manual_admin', 'office_cash', 'office_qr'])
                        ->where('registered_by', $userId));
            });
        });
    }

    private function whereRole(Builder $query, string $role): void
    {
        $webSupport = $this->attribution->webSupportUser();

        $query->where(function (Builder $inner) use ($role, $webSupport): void {
            if ($webSupport && app(\App\Services\RolePermissionService::class)->normalizeRole($webSupport->role) === $role) {
                $inner->orWhereHas('publicRequest');
            }

            $inner->orWhere(function (Builder $office) use ($role): void {
                $office->whereDoesntHave('publicRequest')
                    ->whereHas('initialPayment.registrar', fn (Builder $user) => $user->where('role', $role));
            });
        });
    }

    private function whereChannel(Builder $query, string $channel): void
    {
        match ($channel) {
            AffiliateSupportChannel::WEB => $query->whereHas('publicRequest', fn (Builder $request) => $request
                ->where(function (Builder $inner): void {
                    $inner->whereNull('payment_submitted_at')
                        ->orWhereDoesntHave('payment');
                })),
            AffiliateSupportChannel::EXPRESS => $query->whereHas('publicRequest', fn (Builder $request) => $request
                ->whereNotNull('payment_submitted_at')
                ->whereHas('payment')),
            AffiliateSupportChannel::OFFICE => $query->whereDoesntHave('publicRequest')
                ->whereHas('initialPayment', fn (Builder $payment) => $payment->whereIn('source', ['office_cash', 'office_qr'])),
            AffiliateSupportChannel::INTERNAL => $query->whereDoesntHave('publicRequest')
                ->whereHas('initialPayment', fn (Builder $payment) => $payment->where('source', 'manual_admin')),
            AffiliateSupportChannel::UNKNOWN => $query->whereDoesntHave('publicRequest')
                ->where(function (Builder $inner): void {
                    $inner->whereDoesntHave('initialPayment')
                        ->orWhereHas('initialPayment', fn (Builder $payment) => $payment
                            ->whereNull('registered_by')
                            ->orWhereNotIn('source', ['manual_admin', 'office_cash', 'office_qr']));
                }),
            default => null,
        };
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', 'max:80'],
            'channel' => ['nullable', 'string', 'max:30'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'affiliation_plan_id' => ['nullable', 'integer', 'exists:affiliation_plans,id'],
            'status' => ['nullable', 'string', 'max:60'],
            'payment_status' => ['nullable', 'string', 'max:60'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function summaryCards($affiliates): array
    {
        $resolved = $affiliates->map(fn (Affiliate $affiliate) => $this->attribution->resolve($affiliate));

        return [
            'total' => $affiliates->count(),
            'web' => $resolved->whereIn('channel', [AffiliateSupportChannel::WEB, AffiliateSupportChannel::EXPRESS])->count(),
            'office' => $resolved->whereIn('channel', [AffiliateSupportChannel::OFFICE, AffiliateSupportChannel::INTERNAL])->count(),
            'active_responsibles' => $resolved->pluck('responsible_user.id')->filter()->unique()->count(),
            'approved' => $affiliates->where('status', 'activo')->count(),
            'review' => $affiliates->whereIn('status', ['pago_en_revision', 'pendiente_pago'])->count(),
        ];
    }

    private function responsibleRows($affiliates): array
    {
        return $affiliates
            ->map(function (Affiliate $affiliate): array {
                $resolved = $this->attribution->resolve($affiliate);
                $responsible = $resolved['responsible_user'];
                $key = $responsible?->id ? 'user-'.$responsible->id : 'unknown-'.$resolved['attribution_type'];

                return compact('affiliate', 'resolved', 'responsible', 'key');
            })
            ->groupBy('key')
            ->map(function ($rows): array {
                $first = $rows->first();
                $responsible = $first['responsible'];

                return [
                    'responsible' => $responsible?->name ?? ($first['resolved']['attribution_type'] === 'web_assigned' ? 'Sin responsable configurado' : 'Sin responsable'),
                    'role' => $responsible?->roleLabel() ?? 'No aplica',
                    'web' => $rows->whereIn('resolved.channel', [AffiliateSupportChannel::WEB, AffiliateSupportChannel::EXPRESS])->count(),
                    'office' => $rows->whereIn('resolved.channel', [AffiliateSupportChannel::OFFICE, AffiliateSupportChannel::INTERNAL])->count(),
                    'total' => $rows->count(),
                    'approved' => $rows->where('affiliate.status', 'activo')->count(),
                    'review' => $rows->whereIn('affiliate.status', ['pago_en_revision', 'pendiente_pago'])->count(),
                    'other' => $rows->reject(fn ($row) => in_array($row['affiliate']->status, ['activo', 'pago_en_revision', 'pendiente_pago'], true))->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function responsibleOptions()
    {
        return User::query()
            ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'user_type']);
    }

    private function affiliationStatuses(): array
    {
        return Affiliate::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();
    }

    private function captureDate(Affiliate $affiliate): string
    {
        return $affiliate->publicRequest
            ? SiafcoDate::dateTime($affiliate->publicRequest->submitted_at)
            : SiafcoDate::dateTime($affiliate->created_at);
    }
}
