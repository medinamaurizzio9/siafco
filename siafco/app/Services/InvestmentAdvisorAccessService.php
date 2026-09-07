<?php

namespace App\Services;

use App\Models\InvestmentAdvisor;
use App\Models\InvestmentAdvisorAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InvestmentAdvisorAccessService
{
    private const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function createAccess(InvestmentAdvisor $advisor): array
    {
        return DB::transaction(function () use ($advisor) {
            abort_if($advisor->access()->exists(), 409, 'El asesor ya tiene acceso CRM.');
            $pin = $this->generateTemporaryPin();
            $access = InvestmentAdvisorAccess::create(['investment_advisor_id' => $advisor->id, 'login_code' => $this->generateLoginCode(), 'pin_hash' => Hash::make($pin)]);
            AuditService::record('investment_advisor_access_created', $access, ['advisor_id' => $advisor->id, 'advisor_number' => $advisor->advisor_number, 'access_id' => $access->id]);
            return compact('access', 'pin');
        });
    }

    public function resetPin(InvestmentAdvisorAccess $access): array
    {
        return DB::transaction(function () use ($access) { $pin=$this->generateTemporaryPin(); $access->update(['pin_hash'=>Hash::make($pin),'must_change_pin'=>true,'failed_attempts'=>0,'locked_until'=>null]); AuditService::record('investment_advisor_pin_reset',$access,['advisor_id'=>$access->investment_advisor_id,'access_id'=>$access->id]); return compact('access','pin'); });
    }

    public function enable(InvestmentAdvisorAccess $access): void { $access->update(['is_enabled'=>true]); AuditService::record('investment_advisor_access_enabled',$access,['advisor_id'=>$access->investment_advisor_id,'access_id'=>$access->id]); }
    public function disable(InvestmentAdvisorAccess $access): void { $access->update(['is_enabled'=>false]); AuditService::record('investment_advisor_access_disabled',$access,['advisor_id'=>$access->investment_advisor_id,'access_id'=>$access->id]); }
    public function unlock(InvestmentAdvisorAccess $access): void { $access->update(['failed_attempts'=>0,'locked_until'=>null]); AuditService::record('investment_advisor_access_unlocked',$access,['advisor_id'=>$access->investment_advisor_id,'access_id'=>$access->id]); }
    public function changePin(InvestmentAdvisorAccess $access, string $current, string $new): bool { if(!Hash::check($current,$access->pin_hash)||Hash::check($new,$access->pin_hash)) return false; $access->update(['pin_hash'=>Hash::make($new),'must_change_pin'=>false,'pin_changed_at'=>now(),'failed_attempts'=>0,'locked_until'=>null]); return true; }

    public function generateLoginCode(): string
    {
        for ($attempt=0;$attempt<10;$attempt++) { $code=''; for($i=0;$i<6;$i++) $code.=self::CODE_ALPHABET[random_int(0,strlen(self::CODE_ALPHABET)-1)]; if(!InvestmentAdvisorAccess::where('login_code',$code)->exists()) return $code; }
        throw new \RuntimeException('No se pudo generar un código CRM único.');
    }

    public function generateTemporaryPin(): string { return (string) random_int(100000,999999); }
}
