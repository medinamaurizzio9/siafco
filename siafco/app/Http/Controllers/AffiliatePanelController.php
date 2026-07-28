<?php

namespace App\Http\Controllers;

use App\Models\AffiliateBenefit;
use App\Models\InstitutionalSetting;
use App\Services\CredentialService;

class AffiliatePanelController extends Controller
{
    public function index(CredentialService $credentialService)
    {
        $affiliate = auth()->user()->affiliate?->load('sector', 'plan', 'credential', 'publicRequest.payment');
        $affiliate?->loadCount('payments');
        $latestPayment = $affiliate?->payments()
            ->latest('payment_date')
            ->latest('created_at')
            ->first();
        $isActive = $affiliate?->status === 'activo';
        $credentialData = $affiliate ? $credentialService->presentationData($affiliate, $affiliate->credential) : null;
        $credentialInstitution = InstitutionalSetting::current();
        $benefits = AffiliateBenefit::query()
            ->where('active', true)
            ->when(! $isActive, fn ($query) => $query->where('visible_when_pending', true))
            ->orderBy('order')->orderBy('title')->get();

        return view('affiliate-panel.index', compact(
            'affiliate',
            'benefits',
            'isActive',
            'latestPayment',
            'credentialData',
            'credentialInstitution'
        ));
    }
}
