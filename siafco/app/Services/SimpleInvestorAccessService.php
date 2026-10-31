<?php

namespace App\Services;

use App\Models\SimpleInvestmentInvestor;
use App\Models\SimpleInvestmentInvestorAccess;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SimpleInvestorAccessService
{
    public function issue(SimpleInvestmentInvestor $investor, User $actor): array
    {
        $temporary = Str::password(16, true, true, false);
        $access = SimpleInvestmentInvestorAccess::query()->firstOrNew(['investor_id' => $investor->id]);
        $access->fill([
            'password' => Hash::make($temporary), 'must_change_password' => true, 'is_active' => true,
            'failed_attempts' => 0, 'locked_until' => null, 'session_version' => ((int) $access->session_version) + 1,
            'enabled_by' => $actor->id, 'enabled_at' => now(),
        ])->save();
        AuditService::record($access->wasRecentlyCreated ? 'simple_investor_access_enabled' : 'simple_investor_access_reset', $investor, ['investor_code' => $investor->code]);

        return [$access, $temporary];
    }
}
