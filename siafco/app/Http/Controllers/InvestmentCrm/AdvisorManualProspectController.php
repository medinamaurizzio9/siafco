<?php

namespace App\Http\Controllers\InvestmentCrm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investments\StoreInvestmentProspectManualRequest;
use App\Services\InvestmentAdvisorAuthService;
use App\Services\InvestmentProspectService;

class AdvisorManualProspectController extends Controller
{
    public function create(InvestmentAdvisorAuthService $auth)
    {
        return view('investment-crm.advisor.create-prospect', ['advisor' => $auth->advisor(request())]);
    }

    public function store(StoreInvestmentProspectManualRequest $request, InvestmentAdvisorAuthService $auth, InvestmentProspectService $service)
    {
        $advisor=$auth->advisor($request); $result=$service->createManual($advisor,$request->validated()); $prospect=$result['prospect'];
        if($result['status']==='existing') {
            if($prospect->current_advisor_id===$advisor->id) return redirect()->route('investment-crm.advisor.prospects.show',$prospect)->with('status','Este prospecto ya está registrado en tu CRM.');
            return back()->withErrors(['phone'=>'Este número ya se encuentra registrado en el CRM.'])->withInput($request->safe()->except(['phone']));
        }
        return redirect()->route('investment-crm.advisor.prospects.show',$prospect)->with('status',"Prospecto registrado: {$prospect->prospect_number}.");
    }
}
