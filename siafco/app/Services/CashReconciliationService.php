<?php

namespace App\Services;

use App\Models\AffiliationPayment;
use App\Models\CashDeposit;
use App\Models\User;
use App\Support\PaymentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CashReconciliationService
{
    public function summary(User $cashier): array
    {
        $confirmedCash = (float) AffiliationPayment::query()
            ->where('registered_by', $cashier->id)
            ->where('payment_method', 'efectivo')
            ->whereIn('status', PaymentStatus::confirmedValues())
            ->sum(DB::raw('COALESCE(paid_amount, amount)'));
        $cashUnderReview = (float) AffiliationPayment::query()
            ->where('registered_by', $cashier->id)
            ->where('payment_method', 'efectivo')
            ->where('status', PaymentStatus::UNDER_REVIEW)
            ->sum(DB::raw('COALESCE(paid_amount, amount)'));
        $confirmedDeposits = (float) CashDeposit::query()
            ->where('registered_by', $cashier->id)->where('status', CashDeposit::CONFIRMED)->sum('amount');
        $reviewDeposits = (float) CashDeposit::query()
            ->where('registered_by', $cashier->id)->where('status', CashDeposit::UNDER_REVIEW)->sum('amount');
        $pending = max($confirmedCash - $confirmedDeposits, 0);

        return [
            'confirmed_cash' => $confirmedCash,
            'cash_under_review' => $cashUnderReview,
            'confirmed_deposits' => $confirmedDeposits,
            'review_deposits' => $reviewDeposits,
            'pending_total' => $pending,
            'available' => max($pending - $reviewDeposits, 0),
        ];
    }

    public function create(User $cashier, array $data, ?UploadedFile $voucher): CashDeposit
    {
        $voucherPath = null;
        try {
            return DB::transaction(function () use ($cashier, $data, $voucher, &$voucherPath): CashDeposit {
                User::query()->whereKey($cashier->id)->lockForUpdate()->firstOrFail();
                $available = $this->summary($cashier)['available'];
                $amount = round((float) $data['amount'], 2);
                if ($amount <= 0 || $amount > $available) {
                    throw ValidationException::withMessages([
                        'amount' => 'El monto supera el efectivo disponible para depositar.',
                    ]);
                }
                if ($voucher) {
                    $voucherPath = $voucher->storeAs(
                        'cash-deposits/vouchers',
                        (string) \Illuminate\Support\Str::uuid().'.'.$voucher->extension(),
                        'local'
                    );
                }
                $deposit = CashDeposit::create([
                    'registered_by' => $cashier->id,
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => 'BOB',
                    'deposited_at' => $data['deposited_at'],
                    'transaction_number' => $data['transaction_number'],
                    'voucher_path' => $voucherPath,
                    'observations' => $data['observations'] ?? null,
                    'status' => CashDeposit::UNDER_REVIEW,
                ]);
                AuditService::record('cash_deposit_created', $deposit, [
                    'deposit_id' => $deposit->id,
                    'registered_by' => $cashier->id,
                    'amount' => $deposit->amount,
                    'transaction_number' => $deposit->transaction_number,
                    'status' => $deposit->status,
                ]);

                return $deposit;
            }, 3);
        } catch (\Throwable $exception) {
            if ($voucherPath) {
                Storage::disk('local')->delete($voucherPath);
            }
            throw $exception;
        }
    }

    public function confirm(CashDeposit $deposit, User $reviewer): CashDeposit
    {
        return DB::transaction(function () use ($deposit, $reviewer): CashDeposit {
            $locked = CashDeposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CashDeposit::UNDER_REVIEW) {
                throw ValidationException::withMessages(['deposit' => 'El depósito ya fue revisado.']);
            }
            $locked->update([
                'status' => CashDeposit::CONFIRMED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'confirmed_at' => now(),
                'rejection_reason' => null,
            ]);
            AuditService::record('cash_deposit_confirmed', $locked, [
                'deposit_id' => $locked->id, 'registered_by' => $locked->registered_by,
                'amount' => $locked->amount, 'transaction_number' => $locked->transaction_number,
                'status' => $locked->status, 'reviewed_by' => $reviewer->id,
            ]);
            return $locked;
        }, 3);
    }

    public function reject(CashDeposit $deposit, User $reviewer, string $reason): CashDeposit
    {
        return DB::transaction(function () use ($deposit, $reviewer, $reason): CashDeposit {
            $locked = CashDeposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CashDeposit::UNDER_REVIEW) {
                throw ValidationException::withMessages(['deposit' => 'El depósito ya fue revisado.']);
            }
            $locked->update([
                'status' => CashDeposit::REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'confirmed_at' => null,
                'rejection_reason' => $reason,
            ]);
            AuditService::record('cash_deposit_rejected', $locked, [
                'deposit_id' => $locked->id, 'registered_by' => $locked->registered_by,
                'amount' => $locked->amount, 'transaction_number' => $locked->transaction_number,
                'status' => $locked->status, 'reviewed_by' => $reviewer->id,
            ]);
            return $locked;
        }, 3);
    }
}
