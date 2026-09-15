<?php

namespace App\Http\Controllers;

use App\Models\AffiliateBenefit;
use App\Models\InstitutionalSetting;
use App\Services\CredentialService;

class AffiliatePanelController extends Controller
{
    public function index(CredentialService $credentialService)
    {
        $affiliate = auth()->user()->affiliate()
            ->with('sector', 'plan', 'credential', 'publicRequest.payment')
            ->first();
        $affiliate?->loadCount('payments');
        $latestPayment = $affiliate?->payments()
            ->latest('payment_date')
            ->latest('created_at')
            ->first();
        $isActive = $affiliate?->status === 'activo';
        $hasCredential = (bool) $affiliate?->credential;
        $activeAffiliateStore = (bool) ($isActive && auth()->user()->is_active);
        $credentialData = $affiliate ? $credentialService->presentationData($affiliate, $affiliate->credential) : null;
        $credentialInstitution = InstitutionalSetting::current();
        $benefits = AffiliateBenefit::query()
            ->where('active', true)
            ->when(! $isActive, fn ($query) => $query->where('visible_when_pending', true))
            ->orderBy('order')->orderBy('title')->get();

        $viewData = compact(
            'affiliate',
            'activeAffiliateStore',
            'benefits',
            'hasCredential',
            'isActive',
            'latestPayment',
            'credentialData',
            'credentialInstitution'
        );

        $contentView = match (true) {
            ! $affiliate => 'affiliate-panel.missing',
            ! $isActive => 'affiliate-panel.pending',
            default => 'affiliate-panel.content',
        };

        return view('layouts.app', [
            'title' => 'Inicio',
            'credentialAssets' => $isActive && $hasCredential,
            'content' => view($contentView, $viewData)->render(),
        ]);
    }

    public function benefits()
    {
        $affiliate = auth()->user()->affiliate()
            ->with('sector', 'plan')
            ->first();
        abort_if(! $affiliate, 404);

        $benefits = AffiliateBenefit::query()
            ->where('active', true)
            ->when($affiliate->status !== 'activo', fn ($query) => $query->where('visible_when_pending', true))
            ->orderBy('order')->orderBy('title')->get();

        return view('affiliate-panel.benefits', compact('affiliate', 'benefits'));
    }
}
