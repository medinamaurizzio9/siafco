<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;

class InvestorPanelController extends Controller
{
    public function index()
    {
        $investor = auth()->user()->investor?->load('person', 'lots.periods.receipt', 'receipts');

        abort_unless($investor, 403, 'No tiene un perfil de accionista vinculado.');

        return view('investments.panel.index', compact('investor'));
    }
}
