<?php

namespace App\Http\Controllers;

use App\Models\AffiliateBenefit;

class AffiliatePanelController extends Controller
{
    public function index()
    {
        $affiliate = auth()->user()->affiliate?->load('sector', 'plan', 'payments', 'credential', 'publicRequest.payment');
        $isActive = $affiliate?->status === 'activo';
        $benefits = AffiliateBenefit::query()
            ->where('active', true)
            ->when(! $isActive, fn ($query) => $query->where('visible_when_pending', true))
            ->orderBy('order')->orderBy('title')->get();

        return view('affiliate-panel.index', compact('affiliate', 'benefits', 'isActive'));
    }
}
