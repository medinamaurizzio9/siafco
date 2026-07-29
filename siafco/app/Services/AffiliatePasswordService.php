<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AffiliatePasswordService
{
    public function temporaryPasswordFromCi(?string $ci): string
    {
        $normalized = explode('-', preg_replace('/\s+/u', '', trim((string) $ci)), 2)[0];
        $normalized = preg_replace('/[^A-Za-z0-9]/u', '', $normalized);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'affiliate' => 'No se puede restablecer la contraseña porque el afiliado no tiene CI registrado.',
            ]);
        }

        return $normalized;
    }

    public function assertAllowedPassword(string $password, User $user, ?Affiliate $affiliate): void
    {
        $forbidden = array_filter([
            $affiliate ? $this->temporaryPasswordFromCi($affiliate->ci) : null,
            $affiliate?->registration_number,
            $user->email,
        ]);

        if (collect($forbidden)->contains(fn ($value) => mb_strtolower($password) === mb_strtolower((string) $value))) {
            throw ValidationException::withMessages([
                'password' => 'La nueva contraseña no puede ser igual a tu CI, código de afiliado o correo electrónico.',
            ]);
        }
    }

    public function reset(Affiliate $affiliate): void
    {
        $user = $affiliate->user;
        if (! $user) {
            throw ValidationException::withMessages([
                'affiliate' => 'Este afiliado no tiene una cuenta de acceso vinculada.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($this->temporaryPasswordFromCi($affiliate->ci)),
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
