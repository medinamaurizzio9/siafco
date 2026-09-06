<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectCashDepositRequest;
use App\Http\Requests\StoreCashDepositRequest;
use App\Models\AffiliationPayment;
use App\Models\CashDeposit;
use App\Models\User;
use App\Services\CashReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CashDepositController extends Controller
{
    private const REVIEW_ROLES = ['superadministrador', 'administrador', 'gerente'];

    public function dashboard(Request $request, CashReconciliationService $reconciliation)
    {
        $user = $request->user();
        abort_unless($this->isCashier($user) && $user->hasPermission('cash_deposits.view_own'), 403);

        return view('cash-deposits.dashboard', [
            'summary' => $reconciliation->summary($user),
            'recentPayments' => AffiliationPayment::query()->with('affiliate')
                ->where('registered_by', $user->id)->latest()->limit(8)->get(),
            'recentDeposits' => CashDeposit::query()->with('reviewer')
                ->where('registered_by', $user->id)->latest()->limit(8)->get(),
        ]);
    }

    public function store(StoreCashDepositRequest $request, CashReconciliationService $reconciliation)
    {
        $reconciliation->create($request->user(), $request->validated(), $request->file('voucher'));

        return back()->with('status', 'Depósito registrado. Pendiente de verificación por Gerencia o Administración.');
    }

    public function index(Request $request)
    {
        $this->authorizeReviewer($request, 'cash_deposits.view_all');
        $data = $request->validate([
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in([CashDeposit::UNDER_REVIEW, CashDeposit::CONFIRMED, CashDeposit::REJECTED])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'transaction_number' => ['nullable', 'string', 'max:120'],
        ]);

        $deposits = CashDeposit::query()->with('registrar', 'reviewer')
            ->when($data['cashier_id'] ?? null, fn ($query, $id) => $query->where('registered_by', $id))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['date_from'] ?? null, fn ($query, $date) => $query->whereDate('deposited_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($query, $date) => $query->whereDate('deposited_at', '<=', $date))
            ->when($data['transaction_number'] ?? null, fn ($query, $number) => $query->where('transaction_number', 'like', "%{$number}%"))
            ->latest('deposited_at')->paginate(15)->withQueryString();

        return view('cash-deposits.index', [
            'deposits' => $deposits,
            'filters' => $data,
            'cashiers' => User::query()->whereIn('role', ['caja', 'cajero'])->orderBy('name')->get(),
            'pendingCount' => CashDeposit::where('status', CashDeposit::UNDER_REVIEW)->count(),
            'pendingAmount' => (float) CashDeposit::where('status', CashDeposit::UNDER_REVIEW)->sum('amount'),
        ]);
    }

    public function confirm(Request $request, CashDeposit $deposit, CashReconciliationService $reconciliation)
    {
        $this->authorizeReviewer($request, 'cash_deposits.confirm');
        $reconciliation->confirm($deposit, $request->user());

        return back()->with('status', 'Depósito de caja confirmado.');
    }

    public function show(Request $request, CashDeposit $deposit)
    {
        $this->authorizeReviewer($request, 'cash_deposits.view_all');

        return view('cash-deposits.show', ['deposit' => $deposit->load('registrar', 'reviewer')]);
    }

    public function reject(RejectCashDepositRequest $request, CashDeposit $deposit, CashReconciliationService $reconciliation)
    {
        $reconciliation->reject($deposit, $request->user(), $request->validated('rejection_reason'));

        return back()->with('status', 'Depósito de caja rechazado.');
    }

    public function voucher(Request $request, CashDeposit $deposit)
    {
        $user = $request->user();
        $allowed = $user?->isInternal() && (
            ($this->isCashier($user) && $deposit->registered_by === $user->id && $user->hasPermission('cash_deposits.view_own'))
            || ($user->hasRole(self::REVIEW_ROLES) && $user->hasPermission('cash_deposits.view_all'))
        );
        abort_unless($allowed, 403);
        abort_unless($deposit->voucher_path && Storage::disk('local')->exists($deposit->voucher_path), 404);

        return Storage::disk('local')->response($deposit->voucher_path);
    }

    private function authorizeReviewer(Request $request, string $permission): void
    {
        $user = $request->user();
        abort_unless($user?->isInternal() && $user->hasRole(self::REVIEW_ROLES) && $user->hasPermission($permission), 403);
    }

    private function isCashier(?User $user): bool
    {
        return (bool) ($user?->isInternal() && $user->hasRole(['caja', 'cajero']));
    }
}
