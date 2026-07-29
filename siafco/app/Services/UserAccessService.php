<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserAccessService
{
    public function block(User $user): void
    {
        $user->forceFill(['is_active' => false])->save();
        $this->invalidateSessions($user);
    }

    public function activate(User $user): void
    {
        $user->forceFill(['is_active' => true])->save();
    }

    public function invalidateSessions(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
    }
}
