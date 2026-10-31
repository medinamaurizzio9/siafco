<?php

namespace App\Http\Controllers;

use App\Models\SimpleInvestmentInvestor;
use App\Services\SimpleInvestorAccessService;
use Illuminate\Http\Request;

class SimpleInvestorAccessController extends Controller
{
    public function store(Request $request, SimpleInvestmentInvestor $investor, SimpleInvestorAccessService $service)
    {
        [$access, $temporary] = $service->issue($investor, $request->user());

        return back()->with('portal_temporary_password', $temporary)->with('status', 'Acceso emitido. Entregue la contraseña temporal por un canal verificado; se mostrará una sola vez.');
    }
}
