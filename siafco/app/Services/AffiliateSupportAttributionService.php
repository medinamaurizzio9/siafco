<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Support\AffiliateSupportChannel;
use Illuminate\Support\Facades\Log;

class AffiliateSupportAttributionService
{
    public function resolve(Affiliate $affiliate): array
    {
        $affiliate->loadMissing('publicRequest.payment', 'initialPayment.registrar');

        if ($affiliate->publicRequest) {
            return [
                'responsible_user' => $this->webSupportUser(),
                'attribution_type' => 'web_assigned',
                'channel' => $this->publicChannel($affiliate),
            ];
        }

        $payment = $affiliate->initialPayment;
        if ($payment?->registered_by && $payment->isOfficePayment()) {
            return [
                'responsible_user' => $payment->registrar,
                'attribution_type' => 'internal_registration',
                'channel' => $payment->source === 'manual_admin'
                    ? AffiliateSupportChannel::INTERNAL
                    : AffiliateSupportChannel::OFFICE,
            ];
        }

        return [
            'responsible_user' => null,
            'attribution_type' => 'unknown',
            'channel' => AffiliateSupportChannel::UNKNOWN,
        ];
    }

    public function publicChannel(Affiliate $affiliate): string
    {
        $request = $affiliate->publicRequest;

        if ($request?->payment && $request->payment_submitted_at && $request->submitted_at) {
            $seconds = abs($request->payment_submitted_at->diffInSeconds($request->submitted_at));

            if ($seconds <= 60) {
                return AffiliateSupportChannel::EXPRESS;
            }
        }

        return AffiliateSupportChannel::WEB;
    }

    public function webSupportUser(): ?User
    {
        $id = config('affiliation.web_support_user_id');

        if (filled($id)) {
            $user = User::query()
                ->whereKey($id)
                ->where('is_active', true)
                ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
                ->first();

            if ($user?->isInternal()) {
                return $user;
            }

            Log::warning('Configured affiliation web support user was not found or is inactive.', [
                'web_support_user_id' => $id,
            ]);
        }

        return $this->oldestActiveAdministrator();
    }

    public function oldestActiveAdministrator(): ?User
    {
        $roleService = app(RolePermissionService::class);
        $administratorRoles = collect(array_keys(config('internal_roles.labels', [])))
            ->merge(['admin', 'super_admin', 'super-admin'])
            ->filter(fn (string $role): bool => in_array($roleService->normalizeRole($role), ['superadministrador', 'administrador'], true))
            ->unique()
            ->values()
            ->all();

        return User::query()
            ->where('is_active', true)
            ->whereIn('role', $administratorRoles)
            ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }
}
