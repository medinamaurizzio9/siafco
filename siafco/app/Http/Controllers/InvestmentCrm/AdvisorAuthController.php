<?php
namespace App\Http\Controllers\InvestmentCrm;
use App\Http\Controllers\Controller; use App\Services\InvestmentAdvisorAccessService; use App\Services\InvestmentAdvisorAuthService; use Illuminate\Http\Request;
class AdvisorAuthController extends Controller
{
 public function create(){ return view('investment-crm.advisor.login'); }
 public function store(Request $r, InvestmentAdvisorAuthService $auth){ $d=$r->validate(['login_code'=>['required','string','size:6'],'pin'=>['required','digits:6']]); $result=$auth->attempt($r,$d['login_code'],$d['pin']); if($result!=='success') return back()->withErrors(['login_code'=>$result==='locked'?'Acceso temporalmente bloqueado. Intenta nuevamente más tarde.':'Código o PIN incorrecto.'])->onlyInput('login_code'); return redirect()->route($auth->access($r)->must_change_pin?'investment-crm.advisor.pin.edit':'investment-crm.advisor.dashboard'); }
 public function editPin(){ return view('investment-crm.advisor.change-pin'); }
 public function updatePin(Request $r, InvestmentAdvisorAuthService $auth, InvestmentAdvisorAccessService $service){ $d=$r->validate(['current_pin'=>['required','digits:6'],'pin'=>['required','digits:6','confirmed']]); $access=$auth->access($r); if(!$service->changePin($access,$d['current_pin'],$d['pin'])) return back()->withErrors(['current_pin'=>'El PIN actual es incorrecto o el nuevo PIN no es válido.']); $r->session()->regenerate(); return redirect()->route('investment-crm.advisor.dashboard')->with('status','PIN actualizado.'); }
 public function logout(Request $r, InvestmentAdvisorAuthService $auth){ $auth->logout($r); return redirect()->route('investment-crm.advisor.login')->with('status','Sesión cerrada.'); }
}
