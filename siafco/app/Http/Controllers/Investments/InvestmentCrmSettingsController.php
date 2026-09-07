<?php
namespace App\Http\Controllers\Investments;
use App\Http\Controllers\Controller; use App\Models\InvestmentAdvisor; use App\Services\InvestmentCrmSettingsService; use Illuminate\Http\Request;
class InvestmentCrmSettingsController extends Controller
{
 public function edit(InvestmentCrmSettingsService $s){return view('investments.crm.settings',['settings'=>$s->all(),'previewAdvisor'=>InvestmentAdvisor::where('is_active',true)->first()]);}
 public function update(Request $r,InvestmentCrmSettingsService $s){$rules=array_fill_keys(InvestmentCrmSettingsService::FIELDS,['nullable','string','max:1000']);$s->update($r->validate($rules));return back()->with('status','Configuración del CRM actualizada.');}
 public function restore(InvestmentCrmSettingsService $s){$s->restore();return back()->with('status','Textos predeterminados restaurados.');}
}
