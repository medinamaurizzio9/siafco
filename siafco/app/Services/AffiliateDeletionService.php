<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AuditLog;
use App\Models\CreditApplication;
use App\Models\User;
use App\Support\BenefitRedemptionStatus;
use App\Support\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AffiliateDeletionService
{
    public function delete(Affiliate $affiliate, User $actor, string $reason): array
    {
        $files = [];

        $result = DB::transaction(function () use ($affiliate, $actor, $reason, &$files): array {
            $affiliate = Affiliate::withTrashed()->lockForUpdate()->findOrFail($affiliate->id);
            $affiliate->load([
                'payments', 'credential', 'publicRequest', 'person.investor.lots',
                'person.investor.reservations', 'user', 'benefitRedemptions',
            ]);

            $this->assertDeletable($affiliate);

            $person = $affiliate->person;
            $user = $affiliate->user;
            $publicRequest = $affiliate->publicRequest;
            $payments = $affiliate->payments;
            $credential = $affiliate->credential;

            $files = collect([
                ['public', $affiliate->photo_path],
                ['public', $person?->photo],
                ...$payments->map(fn (AffiliationPayment $payment) => ['local', $payment->voucher_path]),
                ['public', $credential?->qr_path],
                ['public', $credential?->pdf_path],
                ['public', $credential?->png_path],
            ])->filter(fn (array $file) => filled($file[1]))->unique(fn (array $file) => $file[0].':'.$file[1])->values()->all();

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'affiliate_permanently_deleted',
                'auditable_type' => null,
                'auditable_id' => null,
                'metadata' => [
                    'affiliate_id' => $affiliate->id,
                    'person_id' => $person?->id,
                    'ci_masked' => $this->maskCi((string) $affiliate->ci),
                    'reason' => $reason,
                    'payment_statuses' => $payments->pluck('status')->unique()->values()->all(),
                ],
                'ip_address' => request()->ip(),
            ]);

            $payments->each->delete();
            $credential?->delete();
            $affiliate->benefitRedemptions()->delete();
            CreditApplication::where('affiliate_id', $affiliate->id)->delete();
            $publicRequest?->delete();
            $affiliate->forceDelete();

            $userDeleted = false;
            if ($user && $this->userIsExclusive($user, $affiliate->id)) {
                $user->tokens()->delete();
                $user->forceDelete();
                $userDeleted = true;
            }

            $personDeleted = false;
            if ($person && ! $person->affiliate()->exists() && ! $person->investor()->exists() && ! $person->user()->exists()) {
                $person->delete();
                $personDeleted = true;
            }

            return compact('userDeleted', 'personDeleted');
        });

        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $result;
    }

    public function assertDeletable(Affiliate $affiliate): void
    {
        $payments = $affiliate->payments;

        if ($payments->contains(fn (AffiliationPayment $payment) => $payment->status === PaymentStatus::UNDER_REVIEW)) {
            $this->deny('Este registro no puede eliminarse porque tiene un pago en revisión. Primero debe rechazarse o anularse.');
        }

        if ($payments->contains(fn (AffiliationPayment $payment) => PaymentStatus::isConfirmed($payment->status) || filled($payment->receipt_number))) {
            $this->deny('Este registro no puede eliminarse porque tiene un pago confirmado.');
        }

        if ($affiliate->status === 'activo') {
            $this->deny('Este afiliado está activo y no puede eliminarse directamente.');
        }

        if ($affiliate->credential?->isActive()) {
            $this->deny('Este afiliado tiene una credencial activa y no puede eliminarse directamente.');
        }

        $allowedPaymentStatuses = array_unique([
            ...PaymentStatus::pendingValues(), ...PaymentStatus::rejectedValues(), ...PaymentStatus::voidedValues(),
            'failed', 'fallido', 'cancelled', 'cancelado', 'annulled',
        ]);
        if ($payments->contains(fn (AffiliationPayment $payment) => ! in_array($payment->status, $allowedPaymentStatuses, true))) {
            $this->deny('Este registro no puede eliminarse porque tiene movimientos asociados que deben conservarse.');
        }

        $activeCredit = CreditApplication::where('affiliate_id', $affiliate->id)
            ->whereNotIn('status', ['borrador', 'rejected', 'rechazado', 'cancelled', 'cancelado', 'voided', 'anulado', 'closed', 'cerrado', 'paid', 'pagado'])
            ->exists();
        if ($activeCredit) {
            $this->deny('Este registro no puede eliminarse porque tiene un crédito activo.');
        }

        $investor = $affiliate->person?->investor;
        if ($investor && ($investor->status === 'active'
            || $investor->lots->contains(fn ($lot) => in_array($lot->status, ['active_waiting', 'active_earning'], true))
            || $investor->reservations->contains(fn ($reservation) => in_array($reservation->status, ['pending', 'active'], true)))) {
            $this->deny('Este registro no puede eliminarse porque tiene una inversión activa.');
        }

        if ($affiliate->benefitRedemptions->contains(fn ($redemption) => $redemption->status !== BenefitRedemptionStatus::CANCELLED)) {
            $this->deny('Este afiliado tiene un beneficio canjeado vigente y no puede eliminarse.');
        }

        if ($affiliate->storeOrders()->exists()) {
            $this->deny('Este afiliado tiene pedidos registrados y su historial debe conservarse.');
        }
    }

    private function userIsExclusive(User $user, int $affiliateId): bool
    {
        return $user->hasRole('afiliado')
            && ! $user->affiliate()->whereKeyNot($affiliateId)->exists()
            && ! $user->investor()->exists()
            && ! $user->uploadedStoreReceipts()->exists()
            && ! $user->reviewedStoreReceipts()->exists();
    }

    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['affiliate' => $message]);
    }

    private function maskCi(string $ci): string
    {
        return strlen($ci) <= 4 ? str_repeat('*', strlen($ci)) : str_repeat('*', strlen($ci) - 4).substr($ci, -4);
    }
}
