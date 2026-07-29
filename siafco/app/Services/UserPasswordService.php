<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserPasswordService
{
    public function temporaryPasswordFromCi(?string $ci): string
    {
        return app(AffiliatePasswordService::class)->temporaryPasswordFromCi($ci);
    }

    public function setTemporaryPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ])->save();
    }

    public function resetToCi(User $user): void
    {
        $this->setTemporaryPassword($user, $this->temporaryPasswordFromCi($user->ci));
    }
}
