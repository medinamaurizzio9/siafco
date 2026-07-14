<?php

namespace App\Http\Controllers;

class AffiliatePanelController extends Controller
{
    public function index()
    {
        $affiliate = auth()->user()->affiliate?->load('sector', 'plan', 'payments', 'credential');

        return view('affiliate-panel.index', compact('affiliate'));
    }
}
