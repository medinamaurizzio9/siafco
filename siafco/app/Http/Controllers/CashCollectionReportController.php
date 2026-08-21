<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\Sector;
use App\Models\User;
use App\Support\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashCollectionReportController extends Controller
{
    private const REPORT_ROLES = ['superadministrador', 'administrador', 'gerente', 'caja', 'cajero'];
    private const GLOBAL_ROLES = ['superadministrador', 'administrador', 'gerente'];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isInternal() && $user->hasRole(self::REPORT_ROLES), 403);

        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
            'receipt_number' => ['nullable', 'string', 'max:120'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'ci' => ['nullable', 'string', 'max:30'],
            'affiliate_name' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'affiliation_plan_id' => ['nullable', 'integer', 'exists:affiliation_plans,id'],
            'payment_method' => ['nullable', 'string', 'max:40', Rule::in(['efectivo', 'qr', 'transferencia', 'deposito', 'pos', 'otro'])],
            'status' => ['nullable', 'string', Rule::in(PaymentStatus::confirmedValues())],
        ]);

        $canFilterAnyCashier = $user->hasRole(self::GLOBAL_ROLES);
        if (! $canFilterAnyCashier) {
            $data['cashier_id'] = $user->id;
        }

        $baseQuery = $this->query($data);
        $payments = (clone $baseQuery)
            ->with('affiliate.sector', 'affiliate.plan', 'cashier')
            ->latest('confirmed_at')
            ->paginate(15)
            ->withQueryString();

        $summaryRows = (clone $baseQuery)->get(['id', 'paid_amount', 'amount', 'currency', 'payment_method']);
        $totalsByMethod = $summaryRows
            ->groupBy(fn (AffiliationPayment $payment) => $payment->payment_method ?: 'sin_metodo')
            ->map(fn ($rows) => (float) $rows->sum(fn (AffiliationPayment $payment) => (float) ($payment->paid_amount ?? $payment->amount)));

        $pendingQuery = $this->pendingQrQuery($data);
        $pendingPayments = (clone $pendingQuery)
            ->with('affiliate.sector', 'affiliate.plan', 'registrar')
            ->latest('submitted_at')
            ->limit(15)
            ->get();
        $pendingSummary = (clone $pendingQuery)->get(['id', 'paid_amount', 'amount']);

        return view('payments.collections-report', [
            'payments' => $payments,
            'filters' => $data,
            'cashiers' => User::query()
                ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
                ->whereIn('role', self::REPORT_ROLES)
                ->orderBy('name')
                ->get(),
            'sectors' => Sector::orderBy('name')->get(),
            'plans' => AffiliationPlan::orderBy('name')->get(),
            'canFilterAnyCashier' => $canFilterAnyCashier,
            'collectionCount' => $summaryRows->count(),
            'totalAmount' => (float) $summaryRows->sum(fn (AffiliationPayment $payment) => (float) ($payment->paid_amount ?? $payment->amount)),
            'totalsByMethod' => $totalsByMethod,
            'pendingPayments' => $pendingPayments,
            'pendingQrCount' => $pendingSummary->count(),
            'pendingQrAmount' => (float) $pendingSummary->sum(fn (AffiliationPayment $payment) => (float) ($payment->paid_amount ?? $payment->amount)),
        ]);
    }

    private function query(array $filters)
    {
        return AffiliationPayment::query()
            ->whereIn('status', PaymentStatus::confirmedValues())
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('confirmed_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('confirmed_at', '<=', $date))
            ->when($filters['cashier_id'] ?? null, fn ($query, $id) => $query->where('registered_by', $id))
            ->when($filters['receipt_number'] ?? null, fn ($query, $receipt) => $query->where('receipt_number', 'like', "%{$receipt}%"))
            ->when($filters['reference_number'] ?? null, fn ($query, $reference) => $query->where('reference_number', 'like', "%{$reference}%"))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['sector_id'] ?? null, fn ($query, $sectorId) => $query->whereHas('affiliate', fn ($affiliate) => $affiliate->where('sector_id', $sectorId)))
            ->when($filters['affiliation_plan_id'] ?? null, fn ($query, $planId) => $query->whereHas('affiliate', fn ($affiliate) => $affiliate->where('affiliation_plan_id', $planId)))
            ->when($filters['ci'] ?? null, fn ($query, $ci) => $query->whereHas('affiliate', fn ($affiliate) => $affiliate->where('ci', 'like', "%{$ci}%")))
            ->when($filters['affiliate_name'] ?? null, fn ($query, $name) => $query->whereHas('affiliate', fn ($affiliate) => $affiliate->where('full_name', 'like', "%{$name}%")));
    }

    private function pendingQrQuery(array $filters)
    {
        return AffiliationPayment::query()
            ->where('source', 'office_qr')
            ->where('status', PaymentStatus::UNDER_REVIEW)
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('paid_at', '<=', $date))
            ->when($filters['cashier_id'] ?? null, fn ($query, $id) => $query->where('registered_by', $id))
            ->when($filters['reference_number'] ?? null, fn ($query, $reference) => $query->where('reference_number', 'like', "%{$reference}%"))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['sector_id'] ?? null, fn ($query, $sectorId) => $query->whereHas('affiliate', fn ($affiliate) => $affiliate->where('sector_id', $sectorId)))
            ->when($filters['affiliation_plan_id'] ?? null, fn ($query, $planId) => $query->whereHas('affiliate', fn ($affiliate) => $affiliate->where('affiliation_plan_id', $planId)))
            ->when($filters['ci'] ?? null, fn ($query, $ci) => $query->whereHas('affiliate', fn ($affiliate) => $affiliate->where('ci', 'like', "%{$ci}%")))
            ->when($filters['affiliate_name'] ?? null, fn ($query, $name) => $query->whereHas('affiliate', fn ($affiliate) => $affiliate->where('full_name', 'like', "%{$name}%")));
    }
}
