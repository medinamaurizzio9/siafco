<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AffiliateAccountService
{
    public function __construct(private AffiliatePasswordService $passwords)
    {
    }

    public function normalizedCi(?string $ci): string
    {
        return $this->passwords->temporaryPasswordFromCi($ci);
    }

    public function syntheticEmailFromCi(?string $ci): string
    {
        return $this->normalizedCi($ci).'@siafco.com';
    }

    public function ensureForAffiliate(Affiliate $affiliate, Person $person): User
    {
        $affiliate->loadMissing('user');

        if ($affiliate->user) {
            return $affiliate->user;
        }

        $normalizedCi = $this->normalizedCi($affiliate->ci ?: $person->ci);
        $email = $normalizedCi.'@siafco.com';

        $existing = User::query()
            ->where('email', $email)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if ($this->belongsToSameIdentity($existing, $affiliate, $person)) {
                $this->attachExistingUser($existing, $affiliate, $person);

                return $existing->fresh();
            }

            throw ValidationException::withMessages([
                'ci' => 'Ya existe una cuenta de acceso asociada a este CI. Revise el caso con Administración.',
            ]);
        }

        $user = User::create([
            'person_id' => $person->id,
            'name' => $affiliate->full_name,
            'email' => $email,
            'username' => $this->uniqueAffiliateUsername($normalizedCi),
            'ci' => $normalizedCi,
            'phone' => $affiliate->phone ?: $person->phone,
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'is_active' => true,
            'must_change_password' => true,
            'password' => Hash::make($normalizedCi),
        ]);

        $affiliate->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function belongsToSameIdentity(User $user, Affiliate $affiliate, Person $person): bool
    {
        if ($user->person_id) {
            return (int) $user->person_id === (int) $person->id;
        }

        $linkedAffiliate = $user->affiliate;

        return $linkedAffiliate && (int) $linkedAffiliate->id === (int) $affiliate->id;
    }

    private function attachExistingUser(User $user, Affiliate $affiliate, Person $person): void
    {
        if (! $user->person_id) {
            $user->forceFill(['person_id' => $person->id])->save();
        }

        if (! $affiliate->user_id) {
            $affiliate->forceFill(['user_id' => $user->id])->save();
        }
    }

    private function uniqueAffiliateUsername(string $ci): string
    {
        $base = Str::of('afiliado_'.$ci)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        $username = $base !== '' ? $base : 'afiliado';
        $candidate = $username;
        $suffix = 1;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $username.'_'.$suffix++;
        }

        return $candidate;
    }
}
