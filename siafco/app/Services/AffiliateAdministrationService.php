<?php

namespace App\Services;

use App\Events\AffiliateActivated;
use App\Events\AffiliateSuspended;
use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\DigitalCredential;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AffiliateAdministrationService
{
    public function __construct(
        private readonly AffiliatePhotoProcessor $photos,
        private readonly PaymentBalanceService $balances,
        private readonly CredentialService $credentials
    ) {
    }

    public function updatePersonal(Affiliate $affiliate, array $data, User $actor): Affiliate
    {
        return DB::transaction(function () use ($affiliate, $data, $actor): Affiliate {
            $affiliate = Affiliate::query()->with('person', 'user')->lockForUpdate()->findOrFail($affiliate->id);
            $before = $affiliate->only(['phone', 'email', 'address', 'birth_date', 'marital_status']);

            $affiliate->fill($data)->save();
            $affiliate->person?->fill([
                'phone' => $affiliate->phone,
                'email' => $affiliate->email,
                'address' => $affiliate->address,
                'birth_date' => $affiliate->birth_date,
                'marital_status' => $affiliate->marital_status,
            ])->save();
            $affiliate->user?->update(['email' => $affiliate->email]);

            AuditService::record('affiliate_personal_data_updated', $affiliate, [
                'actor_id' => $actor->id,
                'old' => $this->safeDiffValues($before),
                'new' => $this->safeDiffValues($affiliate->only(array_keys($before))),
            ]);

            return $affiliate->fresh(['person', 'user']);
        });
    }

    public function updateInstitutional(Affiliate $affiliate, array $data, User $actor): Affiliate
    {
        return DB::transaction(function () use ($affiliate, $data, $actor): Affiliate {
            $affiliate = Affiliate::query()->with('credential')->lockForUpdate()->findOrFail($affiliate->id);
            $before = $affiliate->only(['regional', 'institution', 'position', 'affiliate_type', 'administrative_notes']);

            $affiliate->fill($data)->save();
            $this->invalidateCredentialFiles($affiliate->credential);

            AuditService::record('affiliate_institutional_data_updated', $affiliate, [
                'actor_id' => $actor->id,
                'old' => $this->safeDiffValues($before),
                'new' => $this->safeDiffValues($affiliate->only(array_keys($before))),
            ]);

            return $affiliate->fresh(['credential']);
        });
    }

    public function changeSector(Affiliate $affiliate, Sector $sector, User $actor): Affiliate
    {
        return DB::transaction(function () use ($affiliate, $sector, $actor): Affiliate {
            $affiliate = Affiliate::query()->with('sector', 'credential')->lockForUpdate()->findOrFail($affiliate->id);
            $sector = Sector::query()->where('is_active', true)->lockForUpdate()->findOrFail($sector->id);
            $old = $affiliate->sector?->only(['id', 'name', 'code']);

            $affiliate->update([
                'sector_id' => $sector->id,
                'regional' => $affiliate->regional ?: $sector->regional,
                'institution' => $affiliate->institution ?: $sector->institution,
            ]);
            $this->invalidateCredentialFiles($affiliate->credential);

            AuditService::record('affiliate_sector_changed', $affiliate, [
                'actor_id' => $actor->id,
                'old' => $old,
                'new' => $sector->only(['id', 'name', 'code']),
                'registration_number_preserved' => $affiliate->registration_number,
            ]);

            return $affiliate->fresh(['sector', 'credential']);
        });
    }

    public function changePlan(Affiliate $affiliate, AffiliationPlan $plan, User $actor): Affiliate
    {
        return DB::transaction(function () use ($affiliate, $plan, $actor): Affiliate {
            $affiliate = Affiliate::query()->with('plan', 'publicRequest')->lockForUpdate()->findOrFail($affiliate->id);
            $plan = AffiliationPlan::query()->where('is_active', true)->lockForUpdate()->findOrFail($plan->id);
            $old = $affiliate->plan?->only(['id', 'name']);

            $affiliate->update(['affiliation_plan_id' => $plan->id]);
            $affiliate->publicRequest?->update([
                'affiliation_plan_id' => $plan->id,
                'amount_due' => $plan->total_amount,
            ]);
            $summary = $this->balances->summary($affiliate->fresh('plan'));

            AuditService::record('affiliate_plan_changed', $affiliate, [
                'actor_id' => $actor->id,
                'old' => $old,
                'new' => $plan->only(['id', 'name']),
                'pending_balance' => $summary['pending_balance'],
            ]);

            return $affiliate->fresh(['plan', 'publicRequest']);
        });
    }

    public function changeStatus(Affiliate $affiliate, string $action, ?string $reason, User $actor): Affiliate
    {
        return DB::transaction(function () use ($affiliate, $action, $reason, $actor): Affiliate {
            $affiliate = Affiliate::query()->with('credential')->lockForUpdate()->findOrFail($affiliate->id);
            $old = $affiliate->status;
            $new = match ($action) {
                'activate', 'reactivate' => 'activo',
                'suspend' => 'suspendido',
                'deactivate' => 'inactivo',
                default => $affiliate->status,
            };

            $affiliate->update([
                'status' => $new,
                'status_changed_at' => now(),
                'status_changed_by' => $actor->id,
                'status_reason' => $reason,
            ]);

            if ($action === 'suspend' || $action === 'deactivate') {
                $this->suspendCredential($affiliate, $actor, $reason ?: 'Cambio de estado administrativo.');
                event(new AffiliateSuspended($affiliate->id, $actor->id, [
                    'previous_status' => $old,
                    'new_status' => $new,
                    'reason' => $reason,
                ]));
            }

            if ($action === 'activate' || $action === 'reactivate') {
                $credential = $affiliate->credential ?: $this->credentials->generate($affiliate);
                $credential->update([
                    'status' => 'vigente',
                    'suspended_at' => null,
                    'suspended_by' => null,
                    'suspension_reason' => null,
                ]);
                event(new AffiliateActivated($affiliate->id, $actor->id, [
                    'previous_status' => $old,
                    'new_status' => $new,
                ]));
            }

            AuditService::record('affiliate_status_changed', $affiliate, [
                'actor_id' => $actor->id,
                'old' => $old,
                'new' => $new,
                'reason' => $reason,
            ]);

            return $affiliate->fresh(['credential']);
        });
    }

    public function updatePhoto(Affiliate $affiliate, UploadedFile $photo, User $actor): Affiliate
    {
        $newPath = $this->photos->process($photo);

        try {
            return DB::transaction(function () use ($affiliate, $newPath, $actor): Affiliate {
                $affiliate = Affiliate::query()->with('person', 'credential')->lockForUpdate()->findOrFail($affiliate->id);
                $oldPath = $affiliate->photo_path;

                $affiliate->update(['photo_path' => $newPath]);
                $affiliate->person?->update(['photo' => $newPath]);
                $this->invalidateCredentialFiles($affiliate->credential);

                AuditService::record('affiliate_photo_updated', $affiliate, [
                    'actor_id' => $actor->id,
                    'old_has_photo' => (bool) $oldPath,
                    'new_has_photo' => true,
                ]);

                if ($oldPath && $oldPath !== $newPath) {
                    Storage::disk('public')->delete($oldPath);
                }

                return $affiliate->fresh(['person', 'credential']);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPath);
            throw $exception;
        }
    }

    public function regenerateCredentialFiles(Affiliate $affiliate, User $actor): DigitalCredential
    {
        $credential = $this->credentials->generate($affiliate);
        $credential->update(['files_invalidated_at' => now()]);

        AuditService::record('affiliate_credential_files_regenerated', $affiliate, [
            'actor_id' => $actor->id,
            'credential_id' => $credential->id,
        ]);

        return $credential;
    }

    public function suspendCredential(Affiliate $affiliate, User $actor, string $reason): ?DigitalCredential
    {
        $credential = $affiliate->credential;
        if (! $credential) {
            return null;
        }

        $credential->update([
            'status' => 'suspendida',
            'suspended_at' => now(),
            'suspended_by' => $actor->id,
            'suspension_reason' => $reason,
        ]);

        AuditService::record('affiliate_credential_suspended', $affiliate, [
            'actor_id' => $actor->id,
            'credential_id' => $credential->id,
            'reason' => $reason,
        ]);

        return $credential;
    }

    public function reactivateCredential(Affiliate $affiliate, User $actor): ?DigitalCredential
    {
        $credential = $affiliate->credential;
        if (! $credential) {
            return null;
        }

        $credential->update([
            'status' => 'vigente',
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);

        AuditService::record('affiliate_credential_reactivated', $affiliate, [
            'actor_id' => $actor->id,
            'credential_id' => $credential->id,
        ]);

        return $credential;
    }

    private function invalidateCredentialFiles(?DigitalCredential $credential): void
    {
        if (! $credential) {
            return;
        }

        foreach ([$credential->pdf_path, $credential->png_path] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }

        $credential->update([
            'pdf_path' => null,
            'png_path' => null,
            'files_invalidated_at' => now(),
        ]);
    }

    private function safeDiffValues(array $values): array
    {
        return collect($values)->except(['password', 'remember_token', 'verification_token'])->all();
    }
}
