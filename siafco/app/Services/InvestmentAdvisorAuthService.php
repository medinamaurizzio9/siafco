<?php

namespace App\Services;

use App\Models\InvestmentAdvisor;
use App\Models\InvestmentAdvisorAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InvestmentAdvisorAuthService
{
    private const SESSION_KEY = 'investment_crm_access_id';

    public function attempt(Request $request, string $code, string $pin): string
    {
        $access=InvestmentAdvisorAccess::with('advisor')->where('login_code',strtoupper(trim($code)))->first();
        if(!$access) return 'invalid';
        if($access->locked_until?->isFuture()) return 'locked';
        if(!$access->is_enabled || !$access->advisor?->is_active) return 'invalid';
        if(!Hash::check($pin,$access->pin_hash)) { $attempts=$access->failed_attempts+1; $access->update(['failed_attempts'=>$attempts,'locked_until'=>$attempts>=5?now()->addMinutes(15):null]); return $attempts>=5?'locked':'invalid'; }
        $access->update(['failed_attempts'=>0,'locked_until'=>null,'last_login_at'=>now(),'last_login_ip'=>$request->ip()]);
        $request->session()->regenerate(); $request->session()->put(self::SESSION_KEY,$access->id);
        AuditService::record('investment_advisor_crm_login_success',$access,['advisor_id'=>$access->investment_advisor_id,'advisor_number'=>$access->advisor->advisor_number,'access_id'=>$access->id]);
        return 'success';
    }

    public function access(Request $request): ?InvestmentAdvisorAccess { $id=$request->session()->get(self::SESSION_KEY); return $id?InvestmentAdvisorAccess::with('advisor')->find($id):null; }
    public function advisor(Request $request): ?InvestmentAdvisor { return $this->access($request)?->advisor; }
    public function check(Request $request): bool { $a=$this->access($request); return (bool)($a?->is_enabled && $a->advisor?->is_active); }
    public function logout(Request $request): void { $request->session()->forget(self::SESSION_KEY); $request->session()->regenerateToken(); }
}
